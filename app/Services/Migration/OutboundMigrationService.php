<?php

namespace App\Services\Migration;

use App\Federation\ActivityBuilders\MigrationActivityBuilder;
use App\Federation\ActivityBuilders\UpdateActivityBuilder;
use App\Federation\Migration;
use App\Jobs\Migration\BuildMigrationManifestJob;
use App\Jobs\Migration\DeliverMigrationActivity;
use App\Jobs\Migration\FetchMigrationMapJob;
use App\Jobs\Migration\FinalizeOutboundMigrationJob;
use App\Jobs\Migration\MoveLocalFollowersJob;
use App\Models\AccountMigration;
use App\Models\AccountMigrationItem;
use App\Models\Follower;
use App\Models\Profile;
use App\Models\User;
use App\Models\Video;
use App\Services\AccountService;
use App\Services\ActivityPubCacheService;
use App\Services\ActivityPubService;
use App\Services\InboxResolverService;
use App\Services\VideoService;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class OutboundMigrationService
{
    public function __construct(
        protected MigrationSettings $settings,
        protected MigrationPeerService $peers,
        protected AttestationService $attestation,
        protected TransitClient $transit,
    ) {}

    public function requiresPassword(User $user): bool
    {
        return blank($user->apple_id);
    }

    public function cooldownEndsAt(Profile $profile): ?CarbonInterface
    {
        $days = $this->settings->cooldownDays();

        if ($days <= 0) {
            return null;
        }

        $last = AccountMigration::inbound()
            ->where('profile_id', $profile->id)
            ->where('state', AccountMigration::STATE_COMPLETED)
            ->latest('completed_at')
            ->value('completed_at');

        if (! $last) {
            return null;
        }

        $ends = Carbon::parse($last)->addDays($days);

        return $ends->isFuture() ? $ends : null;
    }

    public function start(User $user, string $handle, array $scope, ?string $password): AccountMigration
    {
        $profile = $user->profile;

        if (! $this->settings->outboundEnabled()) {
            throw new MigrationException('Moving accounts to another server is not enabled on this server.', 'disabled', 403);
        }

        if (! $profile || ! $profile->local || (int) $profile->status !== 1 || $profile->is_suspended) {
            throw new MigrationException('This account cannot be moved right now.', 'ineligible', 403);
        }

        if ($this->requiresPassword($user) && (! $password || ! Hash::check($password, $user->password))) {
            throw new MigrationException('The password you entered is incorrect.', 'password', 422);
        }

        AccountMigration::outbound()
            ->where('profile_id', $profile->id)
            ->whereIn('state', [AccountMigration::STATE_OFFERED, AccountMigration::STATE_TRANSFERRING])
            ->where('expires_at', '<', now())
            ->get()
            ->each(fn (AccountMigration $stale) => $stale->fail('The destination did not complete the migration in time.'));

        if (AccountMigration::where('profile_id', $profile->id)->active()->exists()) {
            throw new MigrationException('A migration is already in progress for this account.', 'in_progress', 409);
        }

        if ($ends = $this->cooldownEndsAt($profile)) {
            throw new MigrationException('This account moved here recently and can be moved again after '.$ends->toFormattedDayDateString().'.', 'cooldown', 429);
        }

        $attempts = AccountMigration::outbound()
            ->where('profile_id', $profile->id)
            ->where('created_at', '>', now()->subDay())
            ->count();

        if ($attempts >= 5) {
            throw new MigrationException('Too many migration attempts today. Please try again tomorrow.', 'rate_limited', 429);
        }

        $target = $this->peers->resolveHandle($handle);

        if (! $target) {
            throw new MigrationException('We could not find that account. Check the handle and try again.', 'not_found', 404);
        }

        if ($target->local) {
            throw new MigrationException('The new account must be on a different server.', 'same_server', 422);
        }

        $domain = Migration::host($target->uri);

        if (! $domain || $this->peers->isDomainBlocked($domain)) {
            throw new MigrationException('Accounts cannot be moved to that server.', 'blocked', 403);
        }

        $actor = $this->peers->freshActor($target->uri);

        if (! $actor) {
            throw new MigrationException('We could not reach the new account. Try again in a few minutes.', 'unreachable', 502);
        }

        if (! in_array($profile->getActorId(), $this->peers->actorAliases($actor), true)) {
            throw new MigrationException('Add this account as an alias on your new account first, then try again.', 'alias_missing', 422);
        }

        $support = $this->peers->migrationSupport($domain, true);

        if (! $support || ! $support['inbound']) {
            throw new MigrationException('That server does not accept Loops account migrations.', 'unsupported', 422);
        }

        $transit = $this->sharedTransit($support['transit']);

        if (! $transit && $this->settings->requireSeal()) {
            throw new MigrationException('That server does not share a Transit service with this server.', 'transit', 422);
        }

        $target = app(Profile::class)->findOrCreateFromUrl($target->uri, $actor, true) ?? $target;

        $migration = AccountMigration::create([
            'direction' => AccountMigration::OUTBOUND,
            'state' => AccountMigration::STATE_BUILDING,
            'profile_id' => $profile->id,
            'remote_profile_id' => $target->id,
            'local_actor' => $profile->getActorId(),
            'remote_actor' => $target->uri,
            'remote_domain' => $domain,
            'scope' => $this->normalizeScope($scope),
            'transit_url' => $transit,
            'expires_at' => now()->addDays((int) config('loops.migration.intent_ttl_days', 7)),
        ]);

        BuildMigrationManifestJob::dispatch($migration->id);

        return $migration;
    }

    public function normalizeScope(array $scope): array
    {
        $scope = array_values(array_intersect(AccountMigration::SCOPES, $scope));

        if ((in_array('playlists', $scope, true) || in_array('sounds', $scope, true)) && ! in_array('videos', $scope, true)) {
            $scope[] = 'videos';
        }

        return array_values(array_intersect(AccountMigration::SCOPES, $scope));
    }

    public function sharedTransit(array $remote): ?string
    {
        $remote = array_map(fn ($url) => rtrim($url, '/'), $remote);

        foreach ($this->settings->transitUrls() as $url) {
            if (in_array(rtrim($url, '/'), $remote, true)) {
                return rtrim($url, '/');
            }
        }

        return null;
    }

    public function offer(AccountMigration $migration): void
    {
        $profile = $migration->profile;
        $remote = $migration->remoteProfile;

        if (! $profile || ! $remote || ! $remote->inbox_url) {
            $migration->fail('The destination account is no longer reachable.');

            return;
        }

        $payload = [
            'type' => Migration::INTENT_TYPE,
            'version' => Migration::VERSION,
            'id' => $migration->apUrl(),
            'subject' => $migration->local_actor,
            'target' => $migration->remote_actor,
            'source' => Migration::host(config('app.url')),
            'destination' => $migration->remote_domain,
            'scope' => $migration->selectedScopes(),
            'manifest' => [
                'url' => $migration->manifestUrl(),
                'items' => $migration->manifest_items,
                'bytes' => $migration->manifest_bytes,
                'root' => $this->attestation->prefixed($migration->manifest_root),
            ],
            'transit' => $migration->transit_url,
            'issued' => now()->toIso8601ZuluString(),
            'expires' => $migration->expires_at->toIso8601ZuluString(),
        ];

        $migration->intent_jws = $this->attestation->sign($payload);
        $migration->save();

        if ($migration->transit_url) {
            $entry = $this->transit->submitIntent($migration->transit_url, $migration->intent_jws);

            if (! $entry && $this->settings->requireSeal()) {
                $migration->fail('The Transit service did not accept the migration intent.');

                return;
            }

            $migration->seal = array_filter(['intent_entry' => $entry]);
        }

        $migration->forceFill([
            'state' => AccountMigration::STATE_OFFERED,
            'offered_at' => now(),
        ])->save();

        DeliverMigrationActivity::dispatch(
            $profile->getKeyId(),
            $remote->inbox_url,
            MigrationActivityBuilder::offer($migration, $profile)
        );
    }

    public function cancel(AccountMigration $migration): void
    {
        if (! $migration->isCancellable()) {
            throw new MigrationException('This migration can no longer be cancelled.', 'state', 409);
        }

        $migration->forceFill([
            'state' => AccountMigration::STATE_CANCELLED,
            'cancelled_at' => now(),
        ])->save();
    }

    public function handleAccept(AccountMigration $migration, array $activity): void
    {
        if (! in_array($migration->state, [AccountMigration::STATE_OFFERED, AccountMigration::STATE_TRANSFERRING], true)) {
            return;
        }

        $result = is_array($activity['result'] ?? null) ? $activity['result'] : [];
        $receipt = $result['receipt'] ?? null;

        if (! is_string($receipt) || strlen($receipt) > 65536) {
            $migration->fail('The destination sent an invalid receipt.');

            return;
        }

        try {
            $decoded = $this->attestation->verify($receipt, $migration->remote_domain);
        } catch (MigrationException $e) {
            $migration->fail('Receipt verification failed: '.$e->getMessage());

            return;
        }

        $payload = $decoded['payload'];

        $valid = ($payload['type'] ?? null) === Migration::RECEIPT_TYPE
            && (int) ($payload['version'] ?? 0) === Migration::VERSION
            && ($payload['intent'] ?? null) === $migration->apUrl()
            && $this->attestation->unprefixed($payload['intentHash'] ?? null) === hash('sha256', (string) $migration->intent_jws)
            && ($payload['subject'] ?? null) === $migration->local_actor
            && ($payload['target'] ?? null) === $migration->remote_actor
            && $this->attestation->unprefixed($payload['manifestRoot'] ?? null) === $migration->manifest_root
            && Migration::host(data_get($payload, 'map.url')) === $migration->remote_domain;

        if (! $valid) {
            $migration->fail('The receipt does not match this migration.');

            return;
        }

        $seal = (array) $migration->seal;
        $sealJws = $result['seal'] ?? null;

        if ($migration->transit_url && is_string($sealJws)) {
            if ($this->transit->verifySeal($migration->transit_url, ['jws' => $sealJws], $migration->intent_jws, $receipt)) {
                $seal['jws'] = $sealJws;
                $seal['verified_at'] = now()->toIso8601ZuluString();
            }
        }

        if ($this->settings->requireSeal() && empty($seal['jws'])) {
            $migration->fail('The migration was not sealed by a trusted Transit service.');

            return;
        }

        $migration->forceFill([
            'state' => AccountMigration::STATE_TRANSFERRING,
            'receipt_jws' => $receipt,
            'seal' => $seal ?: null,
            'map_root' => $this->attestation->unprefixed(data_get($payload, 'map.root')),
            'accepted_at' => now(),
            'stats' => [
                'receipt' => (array) ($payload['items'] ?? []),
                'map_url' => data_get($payload, 'map.url'),
                'map_items' => (int) data_get($payload, 'map.items', 0),
                'destination_key' => $decoded['key_fingerprint'],
            ],
        ])->save();

        FetchMigrationMapJob::dispatch($migration->id);
    }

    public function handleReject(AccountMigration $migration, array $activity): void
    {
        if (! $migration->isActive() || $migration->state === AccountMigration::STATE_FINALIZING) {
            return;
        }

        $reason = is_string($activity['summary'] ?? null) ? mb_substr(strip_tags($activity['summary']), 0, 200) : 'unknown';

        $migration->forceFill([
            'state' => AccountMigration::STATE_REJECTED,
            'error' => 'The destination declined the migration ('.$reason.').',
            'failed_at' => now(),
        ])->save();
    }

    public function applyMap(AccountMigration $migration): void
    {
        $url = data_get($migration->stats, 'map_url');
        $expected = (int) data_get($migration->stats, 'map_items', 0);

        if (! is_string($url) || Migration::host($url) !== $migration->remote_domain) {
            $migration->fail('The destination did not provide an object map.');

            return;
        }

        $pageSize = (int) config('loops.migration.map_page_size', 200);
        $maxPages = (int) ceil(max(1, $expected) / max(1, $pageSize)) + 1;
        $leaves = [];
        $entries = [];
        $next = $url.(str_contains($url, '?') ? '&' : '?').'page=1';
        $pages = 0;

        while ($next && $pages < $maxPages) {
            $pages++;

            if (Migration::host($next) !== $migration->remote_domain) {
                break;
            }

            $page = app(ActivityPubService::class)->get($next, [], true, true, true, true);

            if (! is_array($page)) {
                throw new MigrationException('Unable to fetch the object map', 'unreachable', 502);
            }

            foreach ((array) ($page['orderedItems'] ?? []) as $entry) {
                $source = $entry['source'] ?? null;
                $target = $entry['target'] ?? null;

                if (! is_string($source) || ! is_string($target)) {
                    continue;
                }

                $leaves[] = $this->attestation->mapLeafHash($source, $target);
                $entries[] = [
                    'source' => $source,
                    'target' => $target,
                    'url' => is_string($entry['url'] ?? null) ? $entry['url'] : null,
                ];
            }

            $next = is_string($page['next'] ?? null) ? $page['next'] : null;
        }

        if (count($entries) !== $expected || $this->attestation->merkleRoot($leaves) !== $migration->map_root) {
            $migration->fail('The object map does not match the signed receipt.');

            return;
        }

        foreach (array_chunk($entries, 200) as $chunk) {
            foreach ($chunk as $entry) {
                if (Migration::host($entry['target']) !== $migration->remote_domain) {
                    continue;
                }

                AccountMigrationItem::forMigration($migration)
                    ->where('source_hash', hash('sha256', $entry['source']))
                    ->update([
                        'target_id' => $entry['target'],
                        'target_url' => $entry['url'] && Migration::host($entry['url']) === $migration->remote_domain ? $entry['url'] : null,
                        'state' => AccountMigrationItem::STATE_DONE,
                        'updated_at' => now(),
                    ]);
            }
        }

        $receipt = (array) data_get($migration->stats, 'receipt', []);
        $failed = (int) ($receipt['failed'] ?? 0);

        $migration->forceFill([
            'state' => AccountMigration::STATE_READY,
            'items_done' => (int) ($receipt['imported'] ?? 0),
            'items_failed' => $failed,
            'items_skipped' => (int) ($receipt['skipped'] ?? 0),
            'bytes_done' => (int) AccountMigrationItem::forMigration($migration)->where('state', AccountMigrationItem::STATE_DONE)->sum('bytes'),
        ])->save();

        if ($failed === 0) {
            FinalizeOutboundMigrationJob::dispatch($migration->id);
        }
    }

    public function requestFinalize(AccountMigration $migration): void
    {
        if ($migration->state !== AccountMigration::STATE_READY) {
            throw new MigrationException('This migration is not ready to be finalized.', 'state', 409);
        }

        FinalizeOutboundMigrationJob::dispatch($migration->id);
    }

    public function finalize(AccountMigration $migration): void
    {
        $lock = Cache::lock('loops:migration:finalize:'.$migration->id, 600);

        if (! $lock->get()) {
            return;
        }

        try {
            $migration->refresh();

            if ($migration->state !== AccountMigration::STATE_READY) {
                return;
            }

            $profile = $migration->profile;
            $actor = $this->peers->freshActor($migration->remote_actor);

            if (! $profile || ! $actor || ! in_array($migration->local_actor, $this->peers->actorAliases($actor), true)) {
                $migration->fail('The new account no longer lists this account as an alias.');

                return;
            }

            $remote = app(Profile::class)->findOrCreateFromUrl($migration->remote_actor, $actor, true);

            if (! $remote) {
                $migration->fail('The new account could not be resolved.');

                return;
            }

            $migration->forceFill(['state' => AccountMigration::STATE_FINALIZING])->save();

            DB::transaction(function () use ($profile, $remote) {
                $profile->forceFill([
                    'status' => 9,
                    'moved_to_profile_id' => $remote->id,
                    'moved_at' => now(),
                    'can_upload' => false,
                    'can_follow' => false,
                    'can_comment' => false,
                    'can_like' => false,
                    'can_share' => false,
                    'can_dm' => false,
                    'can_playlist' => false,
                ])->save();

                if ($user = $profile->user) {
                    $user->forceFill([
                        'can_upload' => false,
                        'can_follow' => false,
                        'can_comment' => false,
                        'can_like' => false,
                        'can_dm' => false,
                        'can_playlist' => false,
                        'can_create_starter_kits' => false,
                        'can_use_starter_kits' => false,
                    ])->save();
                }

                Video::where('profile_id', $profile->id)->where('status', 2)->update(['status' => 7]);
            });

            $this->flushProfileCaches($profile);

            $this->broadcastMove($migration, $profile->fresh(), $remote);

            $migration->forceFill([
                'state' => AccountMigration::STATE_FINALIZED,
                'finalized_at' => now(),
            ])->save();

            MoveLocalFollowersJob::dispatch($migration->id);
        } catch (\Throwable $e) {
            Log::error('Account migration finalize failed', ['migration' => $migration->id, 'error' => $e->getMessage()]);

            $migration->forceFill(['state' => AccountMigration::STATE_READY, 'error' => mb_substr($e->getMessage(), 0, 500)])->save();

            throw $e;
        } finally {
            $lock->release();
        }
    }

    public function broadcastMove(AccountMigration $migration, Profile $profile, Profile $remote): void
    {
        $keyId = $profile->getKeyId();
        $move = MigrationActivityBuilder::move($migration, $profile, $remote->getActorId());
        $update = UpdateActivityBuilder::buildForProfile($profile);
        $jobs = [];

        app(InboxResolverService::class)->chunkFollowerInboxes($profile->id, function ($inboxes) use (&$jobs, $keyId, $move, $update) {
            foreach ($inboxes as $inbox) {
                $jobs[] = new DeliverMigrationActivity($keyId, $inbox['inbox'], $update);
                $jobs[] = new DeliverMigrationActivity($keyId, $inbox['inbox'], $move);
            }
        }, 500);

        if ($remote->inbox_url) {
            $jobs[] = new DeliverMigrationActivity($keyId, $remote->shared_inbox_url ?: $remote->inbox_url, $move);
        }

        if ($jobs === []) {
            return;
        }

        foreach (array_chunk($jobs, 1000) as $chunk) {
            Bus::batch($chunk)
                ->name('Deliver Move '.$migration->uuid)
                ->allowFailures()
                ->onQueue('activitypub-out')
                ->dispatch();
        }
    }

    public function moveLocalFollowers(AccountMigration $migration): int
    {
        $profile = $migration->profile;
        $remote = $migration->remoteProfile;

        if (! $profile || ! $remote) {
            return 0;
        }

        $mover = app(FollowerMoveService::class);
        $spacing = max(0, (int) config('loops.migration.follow_spacing_seconds', 1));
        $moved = 0;

        Follower::where('following_id', $profile->id)
            ->join('profiles', 'profiles.id', '=', 'followers.profile_id')
            ->where('profiles.local', true)
            ->select('followers.id', 'followers.profile_id')
            ->orderBy('followers.id')
            ->chunkById(200, function ($rows) use ($mover, $profile, $remote, $spacing, &$moved) {
                $followers = Profile::whereIn('id', $rows->pluck('profile_id'))->get()->keyBy('id');

                foreach ($rows as $row) {
                    $follower = $followers->get($row->profile_id);

                    if ($follower && $mover->moveFollower($follower, $profile, $remote, (int) floor($moved / 10) * $spacing)) {
                        $moved++;
                    }
                }
            }, 'followers.id', 'id');

        $stats = (array) $migration->stats;
        $stats['local_followers_moved'] = $moved;
        $migration->forceFill(['stats' => $stats])->save();

        return $moved;
    }

    public function flushProfileCaches(Profile $profile): void
    {
        AccountService::del($profile->id);
        app(ActivityPubCacheService::class)->forget(app(ActivityPubCacheService::class)->profileKey((int) $profile->id));

        Video::where('profile_id', $profile->id)->select('id')->chunkById(500, function ($videos) {
            foreach ($videos as $video) {
                VideoService::deleteMediaData($video->id);
            }
        });
    }

    public function redirectFor(Video $video): ?array
    {
        $item = AccountMigrationItem::where('kind', AccountMigrationItem::KIND_VIDEO)
            ->where('local_id', $video->id)
            ->whereNotNull('target_id')
            ->whereHas('migration', function ($q) {
                $q->where('direction', AccountMigration::OUTBOUND)->where('state', AccountMigration::STATE_FINALIZED);
            })
            ->latest('id')
            ->first();

        if (! $item) {
            return null;
        }

        return [
            'id' => $item->target_id,
            'url' => $item->target_url ?: $item->target_id,
        ];
    }
}
