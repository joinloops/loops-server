<?php

namespace App\Services;

use App\Federation\ActivityBuilders\DeleteActivityBuilder;
use App\Jobs\Federation\DeliverDeleteActorActivity;
use App\Jobs\User\UserAccountDeleteJob;
use App\Models\Comment;
use App\Models\CommentReply;
use App\Models\Profile;
use App\Models\StarterKit;
use App\Models\User;
use App\Models\UsernameHistory;
use App\Models\UsernameReservation;
use App\Models\Video;
use App\Models\VideoSound;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Passport\Token;

class AccountDeletionService
{
    public const TOMBSTONE_PREFIX = '~deleted~';

    protected const FEDERATED_KEY = 'loops:account-purge:federated:';

    protected const CHUNK = 200;

    protected float $deadline = INF;

    protected bool $worked = false;

    public function __construct(protected InboxResolverService $resolver) {}

    public function graceDays(): int
    {
        return max(0, (int) config('loops.account_deletion.grace_days', 30));
    }

    public function tombstoneUsername(int|string $profileId): string
    {
        return self::TOMBSTONE_PREFIX.$profileId;
    }

    public function isTombstoneUsername(?string $username): bool
    {
        return $username !== null && str_starts_with($username, self::TOMBSTONE_PREFIX);
    }

    public function canSelfDelete(User $user): bool
    {
        return ! $user->is_admin;
    }

    public function isRestorable(User $user): bool
    {
        $status = (int) $user->status;

        if ($status === 7) {
            return true;
        }

        if ($status !== 8) {
            return false;
        }

        if ($this->isTombstoneUsername($user->username)) {
            return false;
        }

        return ! $user->delete_after || Carbon::parse($user->delete_after)->isFuture();
    }

    public function isLocked(User $user): bool
    {
        return (int) $user->status === 8 && ! $this->isRestorable($user);
    }

    /**
     * Accounts whose grace period has ended and are waiting to be purged.
     *
     * @return Builder<User>
     */
    public function due(): Builder
    {
        return User::query()
            ->where('status', 8)
            ->whereNotNull('delete_after')
            ->where('delete_after', '<=', now())
            ->where(fn ($q) => $q->where('is_admin', false)->orWhereNull('is_admin'));
    }

    public function schedule(User $user, bool $immediate = false): CarbonInterface
    {
        if (! $this->canSelfDelete($user)) {
            throw new \LogicException('Admin accounts cannot be deleted through self-service deletion.');
        }

        $deleteAfter = $immediate ? now() : now()->addDays($this->graceDays());

        $this->markPendingDeletion($user, $deleteAfter);

        app(UserAuditLogService::class)->logAccountDeletionRequested($user, $immediate, $deleteAfter);

        if ($immediate) {
            $this->releaseUsername($user);

            UserAccountDeleteJob::dispatch($user, true, ['source' => 'self_service', 'immediate' => true])
                ->onQueue('default')
                ->afterCommit();
        }

        return $deleteAfter;
    }

    public function deleteByAdmin(User $user, User $admin): void
    {
        if ($user->is_admin) {
            throw new \LogicException('Admin accounts can only be deleted through the CLI.');
        }

        if ((int) $user->id === (int) $admin->id) {
            throw new \LogicException('Admins cannot delete their own account from the dashboard.');
        }

        if ((int) $user->status === 8) {
            $user->forceFill(['delete_after' => now()])->save();
        } else {
            $this->markPendingDeletion($user, now());
        }

        $this->releaseUsername($user);

        UserAccountDeleteJob::dispatch($user, true, ['source' => 'admin', 'immediate' => true, 'deleted_by' => $admin->id])
            ->onQueue('default')
            ->afterCommit();
    }

    protected function markPendingDeletion(User $user, CarbonInterface $deleteAfter): void
    {
        DB::transaction(function () use ($user, $deleteAfter) {
            app(PushTokenCacheService::class)->remove((string) $user->profile_id);

            $user->forceFill([
                'status' => 8,
                'delete_after' => $deleteAfter,
                'remember_token' => Str::random(60),
                'push_token' => null,
                'push_token_platform' => null,
                'push_token_verified_at' => null,
            ])->save();

            $user->profile?->update(['status' => 8]);

            $user->videos()->whereIn('status', [2])->update(['status' => 8]);
            $user->comments()->whereIn('status', ['active'])->update(['status' => 'account_pending_deletion']);
            $user->commentReplies()->whereIn('status', ['active'])->update(['status' => 'account_pending_deletion']);
            $user->actorNotifications()->update(['actor_state' => 8]);
            $user->starterKits()->update([
                'previous_status' => DB::raw('status'),
            ]);
            $user->starterKits()->update(['status' => 8]);

            AccountSuggestionService::removeFromAll($user->profile_id);
            AccountService::del($user->profile_id);

            User::find($user->id)->tokens()->each(function (Token $token) {
                $token->revoke();
                $token->refreshToken?->revoke();
            });
        });

        app(StarterKitService::class)->flushStatsAndPopular();
    }

    public function releaseUsername(User $user): void
    {
        $profileId = $user->profile_id;
        $tombstone = $this->tombstoneUsername($profileId);

        DB::transaction(function () use ($user, $profileId, $tombstone) {
            $now = now();

            User::whereKey($user->id)->lockForUpdate()->first()?->forceFill(['username' => $tombstone])->saveQuietly();

            if ($profileId) {
                Profile::whereKey($profileId)->lockForUpdate()->first()?->forceFill(['username' => $tombstone])->saveQuietly();

                UsernameHistory::where('profile_id', $profileId)
                    ->whereNull('ended_at')
                    ->update(['ended_at' => $now, 'updated_at' => $now]);

                UsernameReservation::where('profile_id', $profileId)->delete();
            }
        });

        $user->username = $tombstone;

        if ($profileId) {
            AccountService::del($profileId);
            app(ActivityPubCacheService::class)->forget(app(ActivityPubCacheService::class)->profileKey((int) $profileId));
        }
    }

    /**
     * Purge an account whose grace period has ended.
     *
     * @return bool true when finished (or nothing to do), false when the time budget ran out and the purge should be resumed
     */
    public function purge(int $userId, ?float $budgetSeconds = null): bool
    {
        $user = User::find($userId);

        if (! $user) {
            return true;
        }

        if ($user->is_admin) {
            Log::warning('Account purge skipped: admin accounts must be deleted through the CLI', ['user_id' => $user->id]);

            return true;
        }

        if ((int) $user->status !== 8 || ! $user->delete_after || Carbon::parse($user->delete_after)->isFuture()) {
            return true;
        }

        $profile = $user->profile_id ? Profile::find($user->profile_id) : null;

        if ($profile && ! $profile->local) {
            Log::error('Account purge aborted: user is linked to a remote profile', ['user_id' => $user->id, 'profile_id' => $profile->id]);

            return true;
        }

        $budget = $budgetSeconds ?? (float) config('loops.account_deletion.job_budget_seconds', 40);
        $this->deadline = $budget > 0 ? microtime(true) + $budget : INF;
        $this->worked = false;

        if (! $this->isTombstoneUsername($user->username) || ($profile && ! $this->isTombstoneUsername($profile->username))) {
            $this->releaseUsername($user);
            $profile?->refresh();
        }

        if ($profile) {
            $steps = [
                'purgeCollaborations',
                'federateDelete',
                'purgeSounds',
                'purgeVideos',
                'purgeReplies',
                'purgeComments',
                'purgeLikes',
                'purgeReposts',
                'purgeBookmarks',
                'purgeFollows',
                'purgeMessages',
                'purgeLive',
                'purgeStarterKits',
                'purgePlaylists',
                'purgeNotifications',
                'purgeProfileRows',
                'purgeAvatar',
            ];

            foreach ($steps as $step) {
                if (! $this->{$step}($profile)) {
                    return false;
                }
            }
        }

        if ($profile) {
            $this->finalizeTombstone($profile);
        }

        $this->purgeUserRows($user);

        if ($profile) {
            Cache::forget(self::FEDERATED_KEY.$profile->id);
        }

        Log::info('Account purged', ['user_id' => $userId, 'profile_id' => $profile?->id]);

        return true;
    }

    protected function hasTime(): bool
    {
        return ! $this->worked || microtime(true) < $this->deadline;
    }

    protected function worked(): void
    {
        $this->worked = true;
    }

    protected function federateDelete(Profile $profile): bool
    {
        if (! app(ConfigService::class)->federation()) {
            return true;
        }

        $key = self::FEDERATED_KEY.$profile->id;

        if (Cache::has($key)) {
            return true;
        }

        $activity = DeleteActivityBuilder::buildForAccount($profile);
        $actorId = $profile->getActorId();
        $keyId = $profile->getKeyId();
        $chunk = max(50, (int) config('loops.account_deletion.fanout_chunk', 500));
        $total = 0;

        $this->resolver->chunkAllKnownInboxesFlat(function (Collection $inboxes) use ($activity, $actorId, $keyId, $profile, &$total) {
            $jobs = $inboxes
                ->filter(fn ($inbox) => is_string($inbox) && str_starts_with($inbox, 'https://') && parse_url($inbox, PHP_URL_HOST))
                ->unique()
                ->map(fn ($inbox) => new DeliverDeleteActorActivity($actorId, $keyId, $inbox, $activity))
                ->values()
                ->all();

            if (empty($jobs)) {
                return;
            }

            $total += count($jobs);

            Bus::batch($jobs)
                ->name("Delete Actor {$profile->id}")
                ->allowFailures()
                ->onQueue('activitypub-delete-fanout')
                ->dispatch();
        }, $chunk);

        Cache::put($key, now()->toIso8601String(), now()->addDays(7));

        if (config('logging.dev_log')) {
            Log::info('Account Delete dispatched', ['profile_id' => $profile->id, 'inbox_count' => $total]);
        }

        return true;
    }

    protected function purgeCollaborations(Profile $profile): bool
    {
        $this->safely(fn () => app(CollabService::class)->purgeForProfile($profile));

        return true;
    }

    protected function purgeSounds(Profile $profile): bool
    {
        $lastId = 0;

        while ($this->hasTime()) {
            $sounds = VideoSound::where('profile_id', $profile->id)
                ->where('id', '>', $lastId)
                ->orderBy('id')
                ->limit(self::CHUNK)
                ->get();

            if ($sounds->isEmpty()) {
                return true;
            }

            foreach ($sounds as $sound) {
                $lastId = $sound->id;

                $usedByOthers = Video::where('sound_id', $sound->id)
                    ->where('profile_id', '!=', $profile->id)
                    ->exists();

                if ($usedByOthers) {
                    $sound->forceFill([
                        'profile_id' => null,
                        'original_video_id' => null,
                        'allow_reuse' => false,
                        'title' => null,
                        'description' => null,
                    ])->save();
                    $sound->recalculateUsage();
                } else {
                    Video::where('sound_id', $sound->id)->update(['sound_id' => null]);
                    DB::table('reports')->where('reported_sound_id', $sound->id)->delete();
                    $sound->delete();
                }
            }

            $this->worked();
        }

        return false;
    }

    protected function purgeVideos(Profile $profile): bool
    {
        $pid = $profile->id;
        $prefix = 'videos/'.$pid.'/';

        while ($this->hasTime()) {
            $videos = Video::where('profile_id', $pid)
                ->orderBy('id')
                ->limit(25)
                ->get(['id', 'profile_id', 'vid', 'vid_optimized', 'duet_path', 'thumbnail_path', 'sound_id']);

            if ($videos->isEmpty()) {
                $this->deleteS3Directory('videos/'.$pid);
                AtomFeedService::invalidate($pid);

                return true;
            }

            $videoIds = $videos->pluck('id')->all();

            foreach ($videos as $video) {
                $this->deleteS3Directory('videos/'.$pid.'/'.$video->id);

                foreach (['vid', 'vid_optimized', 'duet_path', 'thumbnail_path'] as $column) {
                    $this->deleteS3File($video->{$column}, $prefix);
                }
            }

            $playlistIds = DB::table('playlist_video')
                ->whereIn('video_id', $videoIds)
                ->distinct()
                ->pluck('playlist_id')
                ->all();

            $soundIds = $videos->pluck('sound_id')->filter()->unique()->values()->all();

            $commentIds = DB::table('comments')->whereIn('video_id', $videoIds)->pluck('id')->all();
            $replyIds = DB::table('comment_replies')->whereIn('video_id', $videoIds)->pluck('id')->all();

            $recipientIds = DB::table('notifications')->whereIn('video_id', $videoIds)->distinct()->pluck('user_id')->all();

            DB::transaction(function () use ($videoIds, $commentIds, $replyIds) {
                $this->deleteMorphs(Comment::class, $commentIds);
                $this->deleteMorphs(CommentReply::class, $replyIds);
                $this->deleteMorphs(Video::class, $videoIds);

                DB::table('messages')->whereIn('video_id', $videoIds)->update(['video_id' => null]);
                DB::table('live_streams')->whereIn('replay_video_id', $videoIds)->update(['replay_video_id' => null]);
                DB::table('video_sounds')->whereIn('original_video_id', $videoIds)->update(['original_video_id' => null]);

                DB::table('videos')->whereIn('id', $videoIds)->delete();
                app(CollabService::class)->purgeForVideoIds($videoIds);
            });

            foreach ($videoIds as $videoId) {
                VideoService::deleteMediaData($videoId);
                $this->safely(fn () => app(LikeService::class)->clearVideoCache((string) $videoId));
                app(ActivityPubCacheService::class)->forget(app(ActivityPubCacheService::class)->videoKey((int) $videoId));
            }

            foreach ($recipientIds as $recipientId) {
                NotificationService::clearUnreadCount($recipientId);
            }

            $this->recountPlaylists($playlistIds);

            if ($soundIds) {
                VideoSound::whereIn('id', $soundIds)->get()->each->recalculateUsage();
            }

            $this->worked();
        }

        return false;
    }

    protected function purgeReplies(Profile $profile): bool
    {
        while ($this->hasTime()) {
            $replies = DB::table('comment_replies')
                ->where('profile_id', $profile->id)
                ->orderBy('id')
                ->limit(self::CHUNK)
                ->get(['id', 'comment_id', 'video_id']);

            if ($replies->isEmpty()) {
                return true;
            }

            $replyIds = $replies->pluck('id')->all();
            $parentIds = $replies->pluck('comment_id')->unique()->values()->all();
            $videoIds = $replies->pluck('video_id')->unique()->values()->all();

            DB::transaction(function () use ($replyIds) {
                $this->deleteMorphs(CommentReply::class, $replyIds);
                DB::table('comment_replies')->whereIn('id', $replyIds)->delete();
            });

            $this->settleParentComments($parentIds);
            $this->recountVideoComments($videoIds);
            $this->worked();
        }

        return false;
    }

    protected function purgeComments(Profile $profile): bool
    {
        $lastId = 0;

        while ($this->hasTime()) {
            $comments = Comment::withTrashed()
                ->withCount('children')
                ->where('profile_id', $profile->id)
                ->where('id', '>', $lastId)
                ->orderBy('id')
                ->limit(self::CHUNK)
                ->get(['id', 'video_id', 'caption', 'status', 'deleted_at']);

            if ($comments->isEmpty()) {
                return true;
            }

            $lastId = $comments->last()->id;

            $withReplies = $comments->filter(fn ($c) => $c->children_count > 0);
            $withoutReplies = $comments->filter(fn ($c) => $c->children_count === 0);

            $tombstoneIds = $withReplies
                ->reject(fn ($c) => $c->caption === null && $c->status === 'deleted_by_user' && $c->deleted_at !== null)
                ->pluck('id')
                ->all();
            $deleteIds = $withoutReplies->pluck('id')->all();
            $touched = array_merge($tombstoneIds, $deleteIds);

            if (empty($touched)) {
                continue;
            }

            DB::transaction(function () use ($tombstoneIds, $deleteIds, $touched) {
                $this->deleteMorphs(Comment::class, $touched);

                if ($tombstoneIds) {
                    DB::table('comment_likes')->whereIn('comment_id', $tombstoneIds)->delete();
                    DB::table('comment_reposts')->whereIn('comment_id', $tombstoneIds)->delete();
                    DB::table('comment_caption_edits')->whereIn('comment_id', $tombstoneIds)->delete();
                    DB::table('comment_hashtags')->whereIn('comment_id', $tombstoneIds)->delete();
                    DB::table('notifications')->whereIn('comment_id', $tombstoneIds)->whereNull('comment_reply_id')->delete();

                    DB::table('comments')->whereIn('id', $tombstoneIds)->update([
                        'caption' => null,
                        'entities' => null,
                        'status' => 'deleted_by_user',
                        'likes' => 0,
                        'shares' => 0,
                        'has_media' => false,
                        'media_count' => 0,
                        'is_pinned' => false,
                        'deleted_at' => DB::raw('COALESCE(deleted_at, CURRENT_TIMESTAMP)'),
                        'updated_at' => now(),
                    ]);
                }

                if ($deleteIds) {
                    DB::table('comments')->whereIn('id', $deleteIds)->delete();
                }
            });

            foreach ($touched as $commentId) {
                $this->safely(fn () => app(LikeService::class)->clearCommentCache((string) $commentId));
                app(ActivityPubCacheService::class)->forget(app(ActivityPubCacheService::class)->commentKey((int) $commentId));
            }

            $this->recountVideoComments($comments->pluck('video_id')->unique()->values()->all());
            $this->worked();
        }

        return false;
    }

    protected function purgeLikes(Profile $profile): bool
    {
        $pid = $profile->id;
        $likes = app(LikeService::class);

        foreach ([
            ['video_likes', 'video_id', 'videos', fn ($id) => $likes->removeVideoLike((string) $id, (string) $pid)],
            ['comment_likes', 'comment_id', 'comments', fn ($id) => $likes->removeCommentLike((string) $id, (string) $pid)],
            ['comment_reply_likes', 'comment_id', 'comment_replies', fn ($id) => $likes->removeReplyLike((string) $id, (string) $pid)],
        ] as [$table, $column, $target, $forget]) {
            while (true) {
                if (! $this->hasTime()) {
                    return false;
                }

                $ids = DB::table($table)->where('profile_id', $pid)->limit(500)->pluck($column)->unique()->values()->all();

                if (empty($ids)) {
                    break;
                }

                DB::transaction(function () use ($table, $column, $target, $pid, $ids) {
                    DB::table($table)->where('profile_id', $pid)->whereIn($column, $ids)->delete();

                    DB::table($target)->whereIn('id', $ids)->update([
                        'likes' => DB::raw("(SELECT COUNT(*) FROM {$table} WHERE {$table}.{$column} = {$target}.id)"),
                    ]);
                });

                foreach ($ids as $id) {
                    $this->safely(fn () => $forget($id));

                    if ($target === 'videos') {
                        VideoService::deleteMediaData($id);
                    }
                }

                $this->worked();
            }
        }

        return true;
    }

    protected function purgeReposts(Profile $profile): bool
    {
        $pid = $profile->id;

        foreach ([
            ['video_reposts', 'video_id', 'videos'],
            ['comment_reposts', 'comment_id', 'comments'],
            ['comment_reply_reposts', 'reply_id', 'comment_replies'],
        ] as [$table, $column, $target]) {
            while (true) {
                if (! $this->hasTime()) {
                    return false;
                }

                $rows = DB::table($table)->where('profile_id', $pid)->limit(500)->get(['id', $column]);

                if ($rows->isEmpty()) {
                    break;
                }

                DB::transaction(function () use ($table, $column, $target, $rows) {
                    foreach ($rows->groupBy($column) as $targetId => $group) {
                        $n = $group->count();
                        DB::table($target)->where('id', $targetId)->update([
                            'shares' => DB::raw("CASE WHEN shares > {$n} THEN shares - {$n} ELSE 0 END"),
                        ]);
                    }

                    DB::table($table)->whereIn('id', $rows->pluck('id'))->delete();
                });

                if ($target === 'videos') {
                    $rows->pluck($column)->unique()->each(fn ($id) => VideoService::deleteMediaData($id));
                }

                $this->worked();
            }
        }

        return true;
    }

    protected function purgeBookmarks(Profile $profile): bool
    {
        while ($this->hasTime()) {
            $rows = DB::table('video_bookmarks')->where('profile_id', $profile->id)->limit(500)->get(['id', 'video_id']);

            if ($rows->isEmpty()) {
                return true;
            }

            DB::transaction(function () use ($rows) {
                DB::table('videos')->whereIn('id', $rows->pluck('video_id')->unique())->update([
                    'bookmarks' => DB::raw('CASE WHEN bookmarks > 0 THEN bookmarks - 1 ELSE 0 END'),
                ]);
                DB::table('video_bookmarks')->whereIn('id', $rows->pluck('id'))->delete();
            });

            $rows->pluck('video_id')->unique()->each(fn ($id) => VideoService::deleteMediaData($id));
            $this->worked();
        }

        return false;
    }

    protected function purgeFollows(Profile $profile): bool
    {
        $pid = $profile->id;

        foreach ([['profile_id', 'following_id'], ['following_id', 'profile_id']] as [$self, $other]) {
            while (true) {
                if (! $this->hasTime()) {
                    return false;
                }

                $rows = DB::table('followers')->where($self, $pid)->limit(500)->get(['id', $other]);

                if ($rows->isEmpty()) {
                    break;
                }

                $otherIds = $rows->pluck($other)->unique()->values()->all();

                DB::table('followers')->whereIn('id', $rows->pluck('id'))->delete();

                $localIds = Profile::whereIn('id', $otherIds)->where('local', true)->pluck('id')->all();

                foreach ($otherIds as $otherId) {
                    FollowerService::del($pid, $otherId);
                }

                foreach ($localIds as $localId) {
                    $this->safely(fn () => FollowerService::sync($localId));
                    $this->safely(fn () => ShareSheetService::forgetFollowing((int) $localId));
                    $this->safely(fn () => AccountSuggestionService::invalidate((int) $localId));
                }

                $this->worked();
            }
        }

        DB::table('follow_requests')->where('profile_id', $pid)->orWhere('following_id', $pid)->delete();

        $blockers = DB::table('user_filters')->where('account_id', $pid)->pluck('profile_id')->all();
        DB::table('user_filters')->where('profile_id', $pid)->orWhere('account_id', $pid)->delete();

        foreach ($blockers as $blockerId) {
            UserFilterService::getAll($blockerId, true);
        }

        DB::table('hidden_suggestions')->where('profile_id', $pid)->orWhere('account_id', $pid)->delete();

        return true;
    }

    protected function purgeMessages(Profile $profile): bool
    {
        $pid = $profile->id;

        while ($this->hasTime()) {
            $messages = DB::table('messages')->where('profile_id', $pid)->limit(500)->get(['id', 'conversation_id']);

            if ($messages->isEmpty()) {
                break;
            }

            $messageIds = $messages->pluck('id')->all();
            $conversationIds = $messages->pluck('conversation_id')->unique()->values()->all();

            DB::transaction(function () use ($messageIds, $conversationIds) {
                DB::table('dm_media')->whereIn('message_id', $messageIds)->delete();
                DB::table('messages')->whereIn('in_reply_to_id', $messageIds)->update(['in_reply_to_id' => null]);
                DB::table('conversation_participants')->whereIn('last_read_message_id', $messageIds)->update(['last_read_message_id' => null]);
                DB::table('messages')->whereIn('id', $messageIds)->delete();

                foreach ($conversationIds as $conversationId) {
                    $latest = DB::table('messages')
                        ->where('conversation_id', $conversationId)
                        ->whereNull('deleted_at')
                        ->orderByDesc('id')
                        ->first(['id', 'created_at']);

                    DB::table('conversations')->where('id', $conversationId)->update([
                        'last_message_id' => $latest?->id,
                        'last_message_at' => $latest?->created_at,
                    ]);
                }
            });

            DB::table('conversation_participants')
                ->whereIn('conversation_id', $conversationIds)
                ->where('profile_id', '!=', $pid)
                ->pluck('profile_id')
                ->unique()
                ->each(fn ($id) => $this->safely(fn () => ShareSheetService::forget((int) $id)));

            $this->worked();
        }

        if (! $this->hasTime()) {
            return false;
        }

        DB::table('dm_media')->where('profile_id', $pid)->delete();
        DB::table('conversation_participants')->where('profile_id', $pid)->update([
            'last_read_message_id' => null,
            'muted_at' => null,
        ]);

        return true;
    }

    protected function purgeLive(Profile $profile): bool
    {
        $pid = $profile->id;

        $channelIds = DB::table('live_channels')->where('profile_id', $pid)->pluck('id')->all();

        DB::transaction(function () use ($pid, $channelIds) {
            if ($channelIds) {
                $streamIds = DB::table('live_streams')->whereIn('live_channel_id', $channelIds)->pluck('id')->all();

                if ($streamIds) {
                    DB::table('live_stream_messages')->whereIn('live_stream_id', $streamIds)->delete();
                    DB::table('live_streams')->whereIn('id', $streamIds)->delete();
                }

                DB::table('live_channel_bans')->whereIn('live_channel_id', $channelIds)->delete();
                DB::table('live_channel_moderators')->whereIn('live_channel_id', $channelIds)->delete();
                DB::table('live_channels')->whereIn('id', $channelIds)->delete();
            }

            $ownStreams = DB::table('live_streams')->where('profile_id', $pid)->pluck('id')->all();

            if ($ownStreams) {
                DB::table('live_stream_messages')->whereIn('live_stream_id', $ownStreams)->delete();
                DB::table('live_streams')->whereIn('id', $ownStreams)->delete();
            }

            DB::table('live_stream_messages')->where('profile_id', $pid)->delete();
            DB::table('live_channel_bans')->where('profile_id', $pid)->orWhere('moderator_id', $pid)->delete();
            DB::table('live_channel_moderators')->where('profile_id', $pid)->delete();
        });

        return true;
    }

    protected function purgeStarterKits(Profile $profile): bool
    {
        $pid = $profile->id;
        $kits = app(StarterKitService::class);

        while ($this->hasTime()) {
            $owned = StarterKit::where('profile_id', $pid)->orderBy('id')->limit(50)->get(['id']);

            if ($owned->isEmpty()) {
                break;
            }

            foreach ($owned as $kit) {
                $this->deleteS3Directory('starterkit/'.$kit->id);
                $kits->forget($kit->id);
            }

            DB::table('private_media_tokens')
                ->where('tokenable_type', StarterKit::class)
                ->whereIn('tokenable_id', $owned->pluck('id'))
                ->delete();

            StarterKit::whereIn('id', $owned->pluck('id'))->delete();
            $this->worked();
        }

        if (! $this->hasTime()) {
            return false;
        }

        $memberOf = DB::table('starter_kit_accounts')->where('profile_id', $pid)->distinct()->pluck('starter_kit_id')->all();
        $usedKits = DB::table('starter_kit_uses')->where('profile_id', $pid)->distinct()->pluck('starter_kit_id')->all();

        DB::table('starter_kit_accounts')->where('profile_id', $pid)->delete();
        DB::table('starter_kit_uses')->where('profile_id', $pid)->delete();
        DB::table('starter_kit_pending_changes')->where('profile_id', $pid)->delete();

        foreach (StarterKit::whereIn('id', $memberOf)->get() as $kit) {
            $this->safely(fn () => $kit->syncAccountCount());
            $kits->forget($kit->id);
        }

        if ($usedKits) {
            DB::table('starter_kits')->whereIn('id', $usedKits)->update([
                'uses' => DB::raw('(SELECT COUNT(*) FROM starter_kit_uses WHERE starter_kit_uses.starter_kit_id = starter_kits.id)'),
            ]);

            foreach ($usedKits as $kitId) {
                $kits->forget($kitId);
            }
        }

        $this->safely(fn () => $kits->flushStatsAndPopular());

        return true;
    }

    protected function purgePlaylists(Profile $profile): bool
    {
        DB::table('playlists')->where('profile_id', $profile->id)->delete();

        return true;
    }

    protected function purgeNotifications(Profile $profile): bool
    {
        $pid = $profile->id;

        while ($this->hasTime()) {
            $rows = DB::table('notifications')
                ->where('profile_id', $pid)
                ->orWhere('user_id', $pid)
                ->limit(1000)
                ->get(['id', 'user_id']);

            if ($rows->isEmpty()) {
                NotificationService::clearUnreadCount($pid);

                return true;
            }

            DB::table('notifications')->whereIn('id', $rows->pluck('id'))->delete();

            $rows->pluck('user_id')->filter()->unique()->each(fn ($id) => NotificationService::clearUnreadCount($id));
            $this->worked();
        }

        return false;
    }

    protected function purgeProfileRows(Profile $profile): bool
    {
        $pid = $profile->id;

        DB::transaction(function () use ($pid) {
            $activityIds = DB::table('activities')->where('profile_id', $pid)->pluck('id');
            if ($activityIds->isNotEmpty()) {
                DB::table('activity_deliveries')->whereIn('activity_id', $activityIds)->delete();
            }
            DB::table('activity_deliveries')->where('profile_id', $pid)->delete();
            DB::table('activities')->where('profile_id', $pid)->delete();

            foreach ([
                'mentions',
                'media_attachments',
                'video_caption_edits',
                'comment_caption_edits',
                'comment_reply_caption_edits',
                'feed_impressions',
                'feed_feedback',
                'user_interests',
                'profile_links',
                'profile_avatars',
                'private_media_tokens',
                'remote_search_imports',
                'username_history',
                'username_reservations',
            ] as $table) {
                DB::table($table)->where('profile_id', $pid)->delete();
            }

            DB::table('quote_authorizations')->where('quoted_profile_id', $pid)->orWhere('quoter_profile_id', $pid)->delete();
            DB::table('reports')->where('reporter_profile_id', $pid)->delete();
        });

        $this->safely(fn () => app(ForYouFeedService::class)->forgetUserInterests($pid));
        $this->safely(fn () => app(ImpressionBloomFilterService::class)->invalidate($pid));
        $this->safely(fn () => ShareSheetService::forget($pid));
        $this->safely(fn () => AccountSuggestionService::removeFromAll($pid));
        $this->safely(fn () => app(PushTokenCacheService::class)->remove((string) $pid));
        UserFilterService::getAll($pid, true);

        return true;
    }

    protected function purgeAvatar(Profile $profile): bool
    {
        $hashid = null;

        try {
            $hashid = HashidService::safeEncode($profile->id);
        } catch (\Throwable $e) {
            Log::warning('Account purge: could not encode avatar hashid', ['profile_id' => $profile->id, 'error' => $e->getMessage()]);
        }

        if ($hashid) {
            $this->deleteS3Directory('avatars/'.$hashid);
        }

        $this->deleteS3Directory('avatars/'.$profile->id);

        return true;
    }

    protected function purgeUserRows(User $user): void
    {
        foreach (DB::table('data_exports')->where('user_id', $user->id)->whereNotNull('file_path')->pluck('file_path') as $path) {
            if (is_string($path) && $path !== '' && ! str_contains($path, '..')) {
                $this->safely(fn () => Storage::disk('exports')->delete($path));
            }
        }

        Cache::forget("acct-data:data-export-history:{$user->id}");

        DB::transaction(function () use ($user) {
            $tokenIds = DB::table('oauth_access_tokens')->where('user_id', $user->id)->pluck('id');

            if ($tokenIds->isNotEmpty()) {
                DB::table('oauth_refresh_tokens')->whereIn('access_token_id', $tokenIds)->delete();
            }

            DB::table('oauth_access_tokens')->where('user_id', $user->id)->delete();
            DB::table('oauth_auth_codes')->where('user_id', $user->id)->delete();
            DB::table('oauth_device_codes')->where('user_id', $user->id)->delete();
            DB::table('personal_access_tokens')->where('tokenable_type', User::class)->where('tokenable_id', $user->id)->delete();
            DB::table('sessions')->where('user_id', $user->id)->delete();
            DB::table('user_legacy_passwords')->where('user_id', $user->id)->delete();
            DB::table('user_cursors')->where('user_id', $user->id)->delete();

            if ($user->email) {
                DB::table('password_reset_tokens')->where('email', $user->email)->delete();
            }

            User::whereKey($user->id)->delete();
        });
    }

    protected function finalizeTombstone(Profile $profile): void
    {
        $profile->forceFill([
            'user_id' => null,
            'username' => $this->tombstoneUsername($profile->id),
            'name' => null,
            'bio' => null,
            'avatar' => null,
            'links' => null,
            'public_key' => null,
            'admin_notes' => null,
            'status' => 8,
            'purged_at' => now(),
            'followers' => 0,
            'following' => 0,
            'video_count' => 0,
            'is_hidden' => true,
            'discoverable' => false,
            'has_playlists' => false,
            'starter_kit_state' => 0,
        ])->saveQuietly();

        AccountService::del($profile->id);
        app(ActivityPubCacheService::class)->forget(app(ActivityPubCacheService::class)->profileKey($profile->id));
    }

    protected function deleteMorphs(string $type, array $ids): void
    {
        if (empty($ids)) {
            return;
        }

        foreach (array_chunk($ids, 500) as $chunk) {
            DB::table('mentions')->where('mentionable_type', $type)->whereIn('mentionable_id', $chunk)->delete();
            DB::table('media_attachments')->where('attachable_type', $type)->whereIn('attachable_id', $chunk)->delete();
        }
    }

    protected function settleParentComments(array $parentIds): void
    {
        foreach (Comment::withTrashed()->whereIn('id', $parentIds)->get() as $parent) {
            $remaining = CommentReply::where('comment_id', $parent->id)->count();

            if ($remaining === 0 && ($parent->trashed() || $parent->status === 'deleted_by_user')) {
                $this->deleteMorphs(Comment::class, [$parent->id]);
                DB::table('comments')->where('id', $parent->id)->delete();

                continue;
            }

            DB::table('comments')->where('id', $parent->id)->update(['replies' => $remaining]);
        }
    }

    protected function recountVideoComments(array $videoIds): void
    {
        foreach (Video::whereIn('id', $videoIds)->get() as $video) {
            $this->safely(fn () => $video->recalculateCommentsCount());
        }
    }

    protected function recountPlaylists(array $playlistIds): void
    {
        if (empty($playlistIds)) {
            return;
        }

        DB::table('playlists')->whereIn('id', $playlistIds)->update([
            'videos_count' => DB::raw('(SELECT COUNT(*) FROM playlist_video WHERE playlist_video.playlist_id = playlists.id)'),
        ]);
    }

    protected function deleteS3Directory(string $directory): void
    {
        $directory = trim($directory, '/');

        if (! preg_match('#^(videos/\d+(/\d+)?|avatars/[A-Za-z0-9_-]+|starterkit/\d+)$#', $directory)) {
            Log::error('Account purge refused to delete an unexpected S3 prefix', ['prefix' => $directory]);

            return;
        }

        try {
            if (! Storage::disk('s3')->deleteDirectory($directory)) {
                Log::warning('Account purge: S3 directory delete reported failure', ['prefix' => $directory]);
            }
        } catch (\Throwable $e) {
            Log::warning('Account purge: S3 directory delete failed', ['prefix' => $directory, 'error' => $e->getMessage()]);
        }
    }

    protected function deleteS3File(?string $path, string $mustStartWith): void
    {
        if (! $path || str_contains($path, '://') || str_contains($path, '..') || ! str_starts_with($path, $mustStartWith)) {
            return;
        }

        try {
            Storage::disk('s3')->delete($path);
        } catch (\Throwable $e) {
            Log::warning('Account purge: S3 file delete failed', ['path' => $path, 'error' => $e->getMessage()]);
        }
    }

    protected function safely(callable $callback): void
    {
        try {
            $callback();
        } catch (\Throwable $e) {
            if (config('logging.dev_log')) {
                Log::warning('Account purge: non-fatal cleanup failed', ['error' => $e->getMessage()]);
            }
        }
    }
}
