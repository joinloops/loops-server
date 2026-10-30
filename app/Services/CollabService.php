<?php

namespace App\Services;

use App\Jobs\PushNotifications\SendPushNotificationJob;
use App\Models\CollabTrust;
use App\Models\Notification;
use App\Models\Profile;
use App\Models\Video;
use App\Models\VideoCollaboration;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class CollabService
{
    public const STATES = ['off', 'mutuals', 'following', 'everyone'];

    public const DEFAULT_STATE = 'following';

    public const COAUTHORS_CACHE_KEY = 'loops:collab:video:';

    public const MAX_PENDING_SENT = 20;

    public function enabled(): bool
    {
        return (bool) config('loops.collabs.enabled', true);
    }

    public function requestTtlDays(): int
    {
        return max(1, (int) config('loops.collabs.request_ttl_days', 7));
    }

    public function inviteBlockedReason(Profile $inviter, Profile $coauthor): ?string
    {
        if (! $this->enabled()) {
            return 'Collaborations are not enabled on this server.';
        }

        if ((string) $inviter->id === (string) $coauthor->id) {
            return 'You cannot collaborate with yourself.';
        }

        if (! $inviter->local || ! $coauthor->local) {
            return 'Collaborators must have an account on this server.';
        }

        if ((int) $coauthor->status !== 1 || (int) $inviter->status !== 1) {
            return 'This account is not available.';
        }

        $user = $coauthor->user;

        if (! $user || $user->delete_after) {
            return 'This account is not available.';
        }

        if (UserFilterService::isBlocking($coauthor->id, $inviter->id) || UserFilterService::isBlocking($inviter->id, $coauthor->id)) {
            return 'This account is not available.';
        }

        $state = in_array($coauthor->collab_state, self::STATES, true) ? $coauthor->collab_state : self::DEFAULT_STATE;

        if ($state === 'off') {
            return 'This account is not accepting collaborations.';
        }

        if ($this->trusts($coauthor->id, $inviter->id)) {
            return null;
        }

        $allowed = $state === 'everyone'
            || ($state === 'following' && FollowerService::follows($coauthor->id, $inviter->id))
            || ($state === 'mutuals'
                && FollowerService::follows($coauthor->id, $inviter->id)
                && FollowerService::follows($inviter->id, $coauthor->id));

        return $allowed ? null : 'This account only accepts collaborations from people they follow.';
    }

    public function canInvite(Profile $inviter, Profile $coauthor): bool
    {
        return $this->inviteBlockedReason($inviter, $coauthor) === null;
    }

    public function trusts($profileId, $trustedProfileId): bool
    {
        return CollabTrust::where('profile_id', $profileId)
            ->where('trusted_profile_id', $trustedProfileId)
            ->exists();
    }

    public function trust(Profile $profile, Profile $trusted): CollabTrust
    {
        if (! $trusted->local || (string) $profile->id === (string) $trusted->id) {
            throw ValidationException::withMessages(['profile_id' => 'You can only trust other accounts on this server.']);
        }

        return CollabTrust::firstOrCreate([
            'profile_id' => $profile->id,
            'trusted_profile_id' => $trusted->id,
        ]);
    }

    public function untrust($profileId, $trustedProfileId): void
    {
        CollabTrust::where('profile_id', $profileId)
            ->where('trusted_profile_id', $trustedProfileId)
            ->delete();
    }

    public function assertCanInvite(Profile $inviter, Profile $coauthor): void
    {
        if ($reason = $this->inviteBlockedReason($inviter, $coauthor)) {
            throw ValidationException::withMessages(['coauthor_id' => $reason]);
        }

        $pending = VideoCollaboration::where('inviter_profile_id', $inviter->id)
            ->where('state', VideoCollaboration::STATE_PENDING)
            ->count();

        if ($pending >= self::MAX_PENDING_SENT) {
            throw ValidationException::withMessages([
                'coauthor_id' => 'You have too many unanswered collaboration invites. Wait for some to be answered first.',
            ]);
        }
    }

    public function invite(Video $video, Profile $inviter, Profile $coauthor): VideoCollaboration
    {
        $this->assertCanInvite($inviter, $coauthor);

        $trusted = $this->trusts($coauthor->id, $inviter->id);

        $collab = VideoCollaboration::create([
            'video_id' => $video->id,
            'profile_id' => $coauthor->id,
            'inviter_profile_id' => $inviter->id,
            'state' => $trusted ? VideoCollaboration::STATE_APPROVED : VideoCollaboration::STATE_PENDING,
            'is_local' => true,
            'auto_approved' => $trusted,
            'responded_at' => $trusted ? now() : null,
            'expires_at' => $trusted ? null : now()->addDays($this->requestTtlDays()),
        ]);

        if ($trusted) {
            NotificationService::collab(Notification::COLLAB_AUTO_ADDED, $coauthor->id, $inviter->id, $video->id, ['collab_id' => (string) $collab->id]);
        } else {
            NotificationService::collab(Notification::COLLAB_REQUEST, $coauthor->id, $inviter->id, $video->id, ['collab_id' => (string) $collab->id]);
        }

        $this->forgetVideo($video->id);

        return $collab;
    }

    public function accept(VideoCollaboration $collab, bool $trustInviter = false): VideoCollaboration
    {
        if (! $collab->is_local || ! in_array($collab->state, [VideoCollaboration::STATE_PENDING, VideoCollaboration::STATE_EXPIRED], true)) {
            throw ValidationException::withMessages(['collab' => 'This invite can no longer be accepted.']);
        }

        $video = $collab->video;
        $inviter = $collab->inviter;
        $coauthor = $collab->profile;

        if (! $video || ! $inviter || ! $coauthor || $this->inviteBlockedByBlocksOrStatus($inviter, $coauthor)) {
            throw ValidationException::withMessages(['collab' => 'This invite is no longer available.']);
        }

        $updated = VideoCollaboration::whereKey($collab->id)
            ->whereIn('state', [VideoCollaboration::STATE_PENDING, VideoCollaboration::STATE_EXPIRED])
            ->update([
                'state' => VideoCollaboration::STATE_APPROVED,
                'responded_at' => now(),
                'expires_at' => null,
                'updated_at' => now(),
            ]);

        if (! $updated) {
            throw ValidationException::withMessages(['collab' => 'This invite has already been answered.']);
        }

        if ($trustInviter) {
            $this->trust($coauthor, $inviter);
        }

        NotificationService::deleteCollab([Notification::COLLAB_REQUEST], $coauthor->id, $video->id);
        NotificationService::collab(Notification::COLLAB_ACCEPTED, $inviter->id, $coauthor->id, $video->id, ['collab_id' => (string) $collab->id]);

        $this->forgetVideo($video->id);
        $this->publishIfReleased($video);

        return $collab->refresh();
    }

    public function decline(VideoCollaboration $collab): VideoCollaboration
    {
        if (! $collab->is_local || ! $collab->isPending()) {
            throw ValidationException::withMessages(['collab' => 'This invite can no longer be declined.']);
        }

        $collab->forceFill([
            'state' => VideoCollaboration::STATE_DECLINED,
            'responded_at' => now(),
        ])->save();

        NotificationService::deleteCollab([Notification::COLLAB_REQUEST], $collab->profile_id, $collab->video_id);
        NotificationService::collab(Notification::COLLAB_DECLINED, $collab->inviter_profile_id, $collab->profile_id, $collab->video_id, [
            'collab_id' => (string) $collab->id,
            'reason' => 'declined',
        ]);

        $this->forgetVideo($collab->video_id);

        return $collab;
    }

    public function expireStale(int $limit = 500): int
    {
        $count = 0;

        VideoCollaboration::where('state', VideoCollaboration::STATE_PENDING)
            ->where('is_local', true)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->orderBy('id')
            ->limit($limit)
            ->get()
            ->each(function (VideoCollaboration $collab) use (&$count) {
                $updated = VideoCollaboration::whereKey($collab->id)
                    ->where('state', VideoCollaboration::STATE_PENDING)
                    ->update(['state' => VideoCollaboration::STATE_EXPIRED, 'updated_at' => now()]);

                if (! $updated) {
                    return;
                }

                $count++;

                NotificationService::deleteCollab([Notification::COLLAB_REQUEST], $collab->profile_id, $collab->video_id);
                NotificationService::collab(Notification::COLLAB_DECLINED, $collab->inviter_profile_id, $collab->profile_id, $collab->video_id, [
                    'collab_id' => (string) $collab->id,
                    'reason' => 'expired',
                ]);

                $this->forgetVideo($collab->video_id);
            });

        return $count;
    }

    public function remove(VideoCollaboration $collab, string $by, bool $notify = true): void
    {
        if (! $collab->is_local || $collab->state === VideoCollaboration::STATE_REMOVED) {
            return;
        }

        $wasApproved = $collab->isApproved();
        $video = $collab->video;

        $collab->forceFill([
            'state' => VideoCollaboration::STATE_REMOVED,
            'responded_at' => $collab->responded_at ?? now(),
            'expires_at' => null,
        ])->save();

        NotificationService::deleteCollab([Notification::COLLAB_REQUEST, Notification::COLLAB_AUTO_ADDED], $collab->profile_id, $collab->video_id);

        $recipient = $by === 'coauthor' ? $collab->inviter_profile_id : $collab->profile_id;
        $actor = $by === 'coauthor' ? $collab->profile_id : $collab->inviter_profile_id;

        if ($notify && ($wasApproved || $by === 'coauthor')) {
            NotificationService::collab(Notification::COLLAB_REMOVED, $recipient, $actor, $collab->video_id, [
                'collab_id' => (string) $collab->id,
                'by' => $by,
            ]);
        }

        $this->forgetVideo($collab->video_id);
        app(ActivityPubCacheService::class)->forget($this->attestationCacheKey($collab->id));

        if (! $video) {
            return;
        }

        if ($wasApproved && $video->isLive() && $video->federated_at && app(ConfigService::class)->federation()) {
            $video->touch();
            app(FederationDispatcher::class)->dispatchCoauthorRemoval($video->fresh(), $collab);
        }

        if (! $wasApproved) {
            $this->publishIfReleased($video);
        }
    }

    public function discardHeldVideo(Video $video): void
    {
        if ($video->isLive() || $video->federated_at || ! $this->blocksPublish($video)) {
            throw ValidationException::withMessages(['video' => 'Only videos waiting on a collaborator can be discarded here.']);
        }

        try {
            $s3Path = 'videos/'.$video->profile_id.'/'.$video->id.'/';
            if (Storage::disk('s3')->exists($s3Path)) {
                Storage::disk('s3')->deleteDirectory($s3Path);
            }
        } catch (\Throwable $e) {
            Log::error('[collab] media cleanup failed for discarded video', [
                'video_id' => $video->id,
                'message' => $e->getMessage(),
            ]);
        }

        Notification::where('video_id', $video->id)->delete();
        NotificationService::clearUnreadCount($video->profile_id);

        $video->delete();
        VideoService::deleteMediaData($video->id);
    }

    public function blocksPublish(Video $video): bool
    {
        if (! $video->is_local) {
            return false;
        }

        return VideoCollaboration::where('video_id', $video->id)
            ->whereIn('state', VideoCollaboration::HOLDING_STATES)
            ->exists();
    }

    public function publishIfReleased(Video $video): void
    {
        $video = $video->fresh();

        if (! $video || $video->isLive() || $this->blocksPublish($video)) {
            return;
        }

        if (! $video->has_processed || $video->processing_status !== 'completed') {
            return;
        }

        $scheduler = app(VideoScheduleService::class);

        if ($video->isScheduled()) {
            if (! $scheduler->shouldHoldForSchedule($video) && $scheduler->claim($video->id)) {
                $scheduler->publish($video->fresh());
            }

            return;
        }

        $this->goLive($video);
    }

    public function goLive(Video $video): bool
    {
        $flipped = DB::table('videos')
            ->where('id', $video->id)
            ->where('status', '!=', 2)
            ->update(['status' => 2, 'updated_at' => now()]);

        $video = $video->fresh();

        if (! $video) {
            return false;
        }

        VideoService::deleteMediaData($video->id);
        AccountService::del($video->profile_id);

        $config = app(ConfigService::class);

        if ($config->federation() && ! $video->federated_at) {
            app(FederationDispatcher::class)->dispatchVideoCreation($video);

            $video->forceFill(['federated_at' => now(), 'ap_published_at' => now()])->save();
        }

        if ($flipped && $config->pushNotifications()) {
            SendPushNotificationJob::dispatch_videoProcessed(
                profileId: $video->profile_id,
                videoId: $video->id,
            );
        }

        return (bool) $flipped;
    }

    public function coauthorIds($videoId): array
    {
        return Cache::remember(self::COAUTHORS_CACHE_KEY.$videoId, now()->addHours(6), function () use ($videoId) {
            return VideoCollaboration::where('video_id', $videoId)
                ->where('state', VideoCollaboration::STATE_APPROVED)
                ->orderBy('id')
                ->pluck('profile_id')
                ->map(fn ($id) => (string) $id)
                ->all();
        });
    }

    public function coauthorAccounts($videoId): array
    {
        return collect($this->coauthorIds($videoId))
            ->map(fn ($id) => AccountService::compact($id, false))
            ->filter()
            ->values()
            ->all();
    }

    public function federatableCollaborations(Video $video): Collection
    {
        if (! $video->is_local) {
            return collect();
        }

        return VideoCollaboration::with('profile')
            ->where('video_id', $video->id)
            ->where('state', VideoCollaboration::STATE_APPROVED)
            ->where('is_local', true)
            ->orderBy('id')
            ->get()
            ->filter(fn (VideoCollaboration $c) => $c->profile && $c->profile->local && (int) $c->profile->status === 1)
            ->values();
    }

    public function forVideo(Video $video): ?VideoCollaboration
    {
        return VideoCollaboration::where('video_id', $video->id)
            ->where('is_local', true)
            ->where('state', '!=', VideoCollaboration::STATE_REMOVED)
            ->orderByDesc('id')
            ->first();
    }

    public function studioSummary(Video $video): ?array
    {
        $collab = $this->forVideo($video);

        if (! $collab) {
            return null;
        }

        return [
            'id' => (string) $collab->id,
            'state' => $collab->stateName(),
            'auto_approved' => (bool) $collab->auto_approved,
            'account' => AccountService::compact($collab->profile_id),
            'expires_at' => $collab->expires_at?->format('c'),
            'responded_at' => $collab->responded_at?->format('c'),
        ];
    }

    public function videoPreview(Video $video): array
    {
        $disk = Storage::disk('s3');
        $thumb = url('/storage/videos/video-placeholder.jpg');

        if ($video->has_thumb) {
            if ($video->thumbnail) {
                $thumb = $video->thumbnail;
            } elseif ($video->thumbnail_path) {
                $thumb = $disk->url($video->thumbnail_path);
            }
        }

        $ready = (bool) $video->has_processed && $video->processing_status === 'completed' && $video->vid_optimized;

        return [
            'id' => (string) $video->id,
            'caption' => $video->caption,
            'thumbnail' => $thumb,
            'src_url' => $ready ? $disk->url($video->vid_optimized) : null,
            'duration' => $video->duration,
            'width' => $video->width,
            'height' => $video->height,
            'is_ready' => (bool) $ready,
            'is_published' => $video->isLive(),
            'url' => $video->isLive() ? $video->shareUrl() : null,
            'scheduled_at' => $video->isScheduled() && $video->scheduled_at
                ? Carbon::parse($video->scheduled_at)->toIso8601String()
                : null,
        ];
    }

    public function attestationCacheKey($collabId): string
    {
        return 'loops:collab:attestation:'.$collabId;
    }

    public function forgetVideo($videoId): void
    {
        Cache::forget(self::COAUTHORS_CACHE_KEY.$videoId);
        VideoService::deleteMediaData($videoId);

        $apCache = app(ActivityPubCacheService::class);
        $apCache->forget($apCache->videoKey((int) $videoId));
    }

    public function severBetween($profileA, $profileB): void
    {
        VideoCollaboration::where('is_local', true)
            ->where('state', '!=', VideoCollaboration::STATE_REMOVED)
            ->where(function ($q) use ($profileA, $profileB) {
                $q->where(function ($q) use ($profileA, $profileB) {
                    $q->where('profile_id', $profileA)->where('inviter_profile_id', $profileB);
                })->orWhere(function ($q) use ($profileA, $profileB) {
                    $q->where('profile_id', $profileB)->where('inviter_profile_id', $profileA);
                });
            })
            ->get()
            ->each(function (VideoCollaboration $collab) {
                $this->safely(fn () => $this->remove($collab, 'creator', notify: false));
            });

        CollabTrust::where(function ($q) use ($profileA, $profileB) {
            $q->where('profile_id', $profileA)->where('trusted_profile_id', $profileB);
        })->orWhere(function ($q) use ($profileA, $profileB) {
            $q->where('profile_id', $profileB)->where('trusted_profile_id', $profileA);
        })->delete();
    }

    public function purgeForProfile(Profile $profile): void
    {
        VideoCollaboration::where('profile_id', $profile->id)
            ->where('is_local', true)
            ->where('state', '!=', VideoCollaboration::STATE_REMOVED)
            ->get()
            ->each(function (VideoCollaboration $collab) {
                $this->safely(fn () => $this->remove($collab, 'coauthor', notify: false));
            });

        $videoIds = VideoCollaboration::where('profile_id', $profile->id)->pluck('video_id')->all();
        VideoCollaboration::where('profile_id', $profile->id)->delete();

        foreach ($videoIds as $videoId) {
            $this->forgetVideo($videoId);
        }

        CollabTrust::where('profile_id', $profile->id)
            ->orWhere('trusted_profile_id', $profile->id)
            ->delete();
    }

    public function purgeForVideoIds(array $videoIds): void
    {
        if (empty($videoIds)) {
            return;
        }

        $collabIds = VideoCollaboration::whereIn('video_id', $videoIds)->pluck('id')->all();

        VideoCollaboration::whereIn('video_id', $videoIds)->delete();

        foreach ($videoIds as $videoId) {
            Cache::forget(self::COAUTHORS_CACHE_KEY.$videoId);
        }

        $apCache = app(ActivityPubCacheService::class);
        foreach ($collabIds as $collabId) {
            $apCache->forget($this->attestationCacheKey($collabId));
        }
    }

    protected function inviteBlockedByBlocksOrStatus(Profile $inviter, Profile $coauthor): bool
    {
        if ((int) $inviter->status !== 1 || (int) $coauthor->status !== 1) {
            return true;
        }

        return UserFilterService::isBlocking($coauthor->id, $inviter->id)
            || UserFilterService::isBlocking($inviter->id, $coauthor->id);
    }

    protected function safely(callable $callback): void
    {
        try {
            $callback();
        } catch (\Throwable $e) {
            Log::warning('[collab] cleanup step failed', ['error' => $e->getMessage()]);
        }
    }
}
