<?php

namespace App\Services\Migration;

use App\Federation\ActivityBuilders\MigrationActivityBuilder;
use App\Federation\Migration;
use App\Federation\Validators\NoteWithVideoAttachmentValidator;
use App\Jobs\Federation\DeliverBlockActivity;
use App\Jobs\Federation\DeliverFollowRequest;
use App\Jobs\Federation\ProcessRemoteVideoJob;
use App\Jobs\Migration\DeliverMigrationActivity;
use App\Jobs\Migration\FinalizeInboundMigrationJob;
use App\Jobs\Migration\ImportMigrationGraphJob;
use App\Jobs\Migration\ImportMigrationRelationsJob;
use App\Jobs\Migration\ImportMigrationVideoJob;
use App\Jobs\Migration\PlanInboundMigrationJob;
use App\Jobs\Migration\ResolveMigrationBookmarkJob;
use App\Jobs\Video\VideoOptimizeJob;
use App\Jobs\Video\VideoThumbnailJob;
use App\Models\AccountMigration;
use App\Models\AccountMigrationItem;
use App\Models\Follower;
use App\Models\FollowRequest;
use App\Models\Profile;
use App\Models\UserFilter;
use App\Models\Video;
use App\Models\VideoBookmark;
use App\Models\VideoLike;
use App\Models\VideoRepost;
use App\Models\VideoSound;
use App\Services\AccountService;
use App\Services\ActivityPubService;
use App\Services\AvatarService;
use App\Services\BlockedDomainService;
use App\Services\FederationDispatcher;
use App\Services\LinkLimitService;
use App\Services\SanitizeService;
use App\Services\UserFilterService;
use App\Services\VideoService;
use Illuminate\Bus\Batch;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class InboundMigrationService
{
    public const KINDS = ['profile', 'video', 'sound', 'playlist', 'follow', 'block', 'bookmark'];

    public function __construct(
        protected MigrationSettings $settings,
        protected MigrationPeerService $peers,
        protected AttestationService $attestation,
        protected MediaTransferService $media,
        protected TransitClient $transit,
    ) {}

    public function handleOffer(array $activity, Profile $actor, Profile $target): void
    {
        $object = $activity['object'];
        $migrationId = $object['id'];
        $actorHost = Migration::host($actor->uri);

        if (! $target->local || (int) $target->status !== 1 || ! $actorHost || $actor->local) {
            return;
        }

        if ($this->peers->isDomainBlocked($actorHost)) {
            return;
        }

        if (AccountMigration::inbound()->where('remote_migration_hash', hash('sha256', $migrationId))->exists()) {
            return;
        }

        if (! $this->settings->inboundEnabled()) {
            $this->reject($target, $actor, $migrationId, 'inbound_disabled');

            return;
        }

        if (! in_array($actor->uri, $target->aliases(), true)) {
            $this->reject($target, $actor, $migrationId, 'alias_missing');

            return;
        }

        if (AccountMigration::where('profile_id', $target->id)->active()->exists()) {
            $this->reject($target, $actor, $migrationId, 'in_progress');

            return;
        }

        try {
            $decoded = $this->attestation->verify((string) $object['intent'], $actorHost);
        } catch (MigrationException $e) {
            $this->reject($target, $actor, $migrationId, 'invalid_intent');

            return;
        }

        $intent = $decoded['payload'];
        $manifestUrl = data_get($intent, 'manifest.url');
        $items = data_get($intent, 'manifest.items');
        $root = $this->attestation->unprefixed(data_get($intent, 'manifest.root'));
        $expires = strtotime((string) ($intent['expires'] ?? ''));

        $valid = ($intent['type'] ?? null) === Migration::INTENT_TYPE
            && (int) ($intent['version'] ?? 0) === Migration::VERSION
            && ($intent['id'] ?? null) === $migrationId
            && Migration::host($migrationId) === $actorHost
            && ($intent['subject'] ?? null) === $actor->uri
            && ($intent['target'] ?? null) === $target->getActorId()
            && ($intent['destination'] ?? null) === Migration::host(config('app.url'))
            && ($intent['source'] ?? null) === $actorHost
            && is_string($manifestUrl)
            && $manifestUrl === ($object['manifest'] ?? null)
            && Migration::host($manifestUrl) === $actorHost
            && is_int($items) && $items >= 0 && $items <= 250000
            && is_string($root) && strlen($root) === 64
            && $expires && $expires > time();

        if (! $valid) {
            $this->reject($target, $actor, $migrationId, 'invalid_intent');

            return;
        }

        $transit = $object['transit'] ?? ($intent['transit'] ?? null);
        $transit = is_string($transit) && $this->settings->trustsTransit($transit) ? rtrim($transit, '/') : null;

        if (! $transit && $this->settings->requireSeal()) {
            $this->reject($target, $actor, $migrationId, 'transit_required');

            return;
        }

        $migration = AccountMigration::create([
            'direction' => AccountMigration::INBOUND,
            'state' => AccountMigration::STATE_PENDING,
            'profile_id' => $target->id,
            'remote_profile_id' => $actor->id,
            'local_actor' => $target->getActorId(),
            'remote_actor' => $actor->uri,
            'remote_domain' => $actorHost,
            'remote_migration_id' => $migrationId,
            'remote_migration_hash' => hash('sha256', $migrationId),
            'scope' => array_values(array_intersect(AccountMigration::SCOPES, (array) ($intent['scope'] ?? []))),
            'manifest_root' => $root,
            'manifest_items' => $items,
            'manifest_bytes' => max(0, (int) data_get($intent, 'manifest.bytes', 0)),
            'intent_jws' => (string) $object['intent'],
            'transit_url' => $transit,
            'expires_at' => now()->setTimestamp($expires),
            'stats' => ['source_key' => $decoded['key_fingerprint']],
        ]);

        PlanInboundMigrationJob::dispatch($migration->id);
    }

    public function reject(Profile $target, Profile $actor, string $migrationId, string $reason): void
    {
        if (! $actor->inbox_url) {
            return;
        }

        DeliverMigrationActivity::dispatch(
            $target->getKeyId(),
            $actor->inbox_url,
            MigrationActivityBuilder::reject($target, $actor->uri, $migrationId, $reason)
        );
    }

    public function plan(AccountMigration $migration): void
    {
        if ($migration->state !== AccountMigration::STATE_PENDING) {
            return;
        }

        $intent = $this->attestation->decode((string) $migration->intent_jws)['payload'];
        $manifestUrl = (string) data_get($intent, 'manifest.url');
        $expected = (int) $migration->manifest_items;

        AccountMigrationItem::forMigration($migration)->delete();

        $position = 0;
        $leaves = [];
        $buffer = [];
        $pages = 0;
        $next = $manifestUrl.(str_contains($manifestUrl, '?') ? '&' : '?').'page=1';

        while ($next) {
            if (++$pages > $expected + 2 || Migration::host($next) !== $migration->remote_domain) {
                throw new MigrationException('The manifest is longer than announced', 'manifest_mismatch');
            }

            $page = app(ActivityPubService::class)->get($next, [], true, true, true, true);

            if (! is_array($page)) {
                throw new MigrationException('Unable to fetch the manifest', 'unreachable', 502);
            }

            foreach ((array) ($page['orderedItems'] ?? []) as $entry) {
                $position++;

                if ($position > $expected || ! $this->validEntry($entry, $position)) {
                    throw new MigrationException('The manifest contains an invalid entry', 'manifest_mismatch');
                }

                $leaf = $this->attestation->leafHash($position, $entry['kind'], $entry['id'], $entry['data']);
                $leaves[] = $leaf;
                $now = now();

                $buffer[] = [
                    'migration_id' => $migration->id,
                    'position' => $position,
                    'kind' => $entry['kind'],
                    'source_id' => $entry['id'],
                    'source_hash' => hash('sha256', $entry['id']),
                    'payload' => json_encode($entry['data'], AttestationService::JSON_FLAGS),
                    'leaf_hash' => $leaf,
                    'state' => AccountMigrationItem::STATE_PENDING,
                    'bytes' => 0,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];

                if (count($buffer) >= 200) {
                    AccountMigrationItem::insert($buffer);
                    $buffer = [];
                }
            }

            $next = is_string($page['next'] ?? null) ? $page['next'] : null;
        }

        if ($buffer !== []) {
            AccountMigrationItem::insert($buffer);
        }

        if ($position !== $expected || $this->attestation->merkleRoot($leaves) !== $migration->manifest_root) {
            AccountMigrationItem::forMigration($migration)->delete();
            $migration->fail('The manifest does not match the signed intent.');

            if ($migration->remoteProfile && $migration->profile) {
                $this->reject($migration->profile, $migration->remoteProfile, $migration->remote_migration_id, 'manifest_mismatch');
            }

            return;
        }

        $migration->forceFill([
            'state' => AccountMigration::STATE_TRANSFERRING,
            'items_total' => $expected,
            'accepted_at' => now(),
        ])->save();

        $this->dispatchImports($migration);
    }

    public function dispatchImports(AccountMigration $migration): void
    {
        $profileItem = AccountMigrationItem::forMigration($migration)->where('kind', AccountMigrationItem::KIND_PROFILE)->where('state', AccountMigrationItem::STATE_PENDING)->first();

        if ($profileItem) {
            try {
                $this->importProfile($migration, $profileItem);
            } catch (\Throwable $e) {
                $profileItem->markFailed($e->getMessage());
            }
        }

        $jobs = AccountMigrationItem::forMigration($migration)
            ->where('kind', AccountMigrationItem::KIND_VIDEO)
            ->where('state', AccountMigrationItem::STATE_PENDING)
            ->orderBy('position')
            ->pluck('id')
            ->map(fn ($id) => new ImportMigrationVideoJob($migration->id, (int) $id))
            ->all();

        $migrationId = $migration->id;

        if ($jobs === []) {
            ImportMigrationRelationsJob::dispatch($migrationId);

            return;
        }

        Bus::batch($jobs)
            ->name('Import migration videos '.$migration->uuid)
            ->allowFailures()
            ->onConnection('redis-migration')
            ->onQueue('migration')
            ->finally(function (Batch $batch) use ($migrationId) {
                ImportMigrationRelationsJob::dispatch($migrationId);
            })
            ->dispatch();
    }

    public function sourceCancelled(AccountMigration $migration): bool
    {
        $status = app(ActivityPubService::class)->get($migration->remote_migration_id, [], true, true, true, true);

        if (! is_array($status) || ! in_array($status['state'] ?? null, [AccountMigration::STATE_CANCELLED, AccountMigration::STATE_FAILED, AccountMigration::STATE_REJECTED], true)) {
            return false;
        }

        $migration->fail('The source server cancelled the migration.');

        return true;
    }

    public function importProfile(AccountMigration $migration, AccountMigrationItem $item): void
    {
        $profile = $migration->profile;
        $data = (array) $item->payload;
        $sanitize = app(SanitizeService::class);

        $name = $sanitize->cleanPlainTextWithoutLineBreaks((string) ($data['name'] ?? ''));
        $bio = $sanitize->cleanPlainTextWithAllowedLineBreaks($this->qualifyMentions((string) ($data['bio'] ?? ''), $migration->remote_domain));

        $updates = [];

        if ($name) {
            $updates['name'] = Str::limit($name, 64, '');
        }

        if ($bio !== null) {
            $updates['bio'] = Str::limit((string) $bio, 2000, '');
        }

        if ($updates) {
            $profile->forceFill($updates)->save();

            if (isset($updates['name']) && $profile->user) {
                $profile->user->forceFill(['name' => $updates['name']])->save();
            }
        }

        $bytes = 0;

        if (! empty($data['avatar']['sha256'])) {
            try {
                $url = $data['avatar']['url'] ?? null;

                if (is_string($url)) {
                    $max = (int) config('loops.migration.max_image_mb', 10) * 1024 * 1024;
                    $contents = $this->media->fetchBytes($url, $max, $data['avatar']['sha256']);
                    $temp = $this->media->writeTemp($contents, 'img');

                    try {
                        AvatarService::updateAvatar($profile, $temp);
                        $bytes = strlen($contents);
                    } finally {
                        @unlink($temp);
                    }
                }
            } catch (\Throwable $e) {
                Log::info('Migration avatar import skipped', ['migration' => $migration->id, 'error' => $e->getMessage()]);
            }
        }

        $existing = $profile->profileLinks()->pluck('url')->all();
        $added = false;

        foreach (array_slice((array) ($data['links'] ?? []), 0, 10) as $link) {
            $url = is_array($link) ? ($link['url'] ?? null) : null;

            if (! is_string($url) || strlen($url) > 120 || in_array($url, $existing, true) || ! str_starts_with($url, 'https://')) {
                continue;
            }

            if (! LinkLimitService::canAddLink($profile->fresh()) || ! BlockedDomainService::validateUrl($url)['valid']) {
                continue;
            }

            $profile->profileLinks()->create([
                'url' => $url,
                'position' => ($profile->profileLinks()->max('position') ?? -1) + 1,
            ]);

            $existing[] = $url;
            $added = true;
        }

        if ($added) {
            $profile->syncLinksJson();
        }

        AccountService::del($profile->id);

        $item->markDone(localId: $profile->id, targetId: $profile->getActorId(), bytes: $bytes);
    }

    public function importVideo(AccountMigration $migration, AccountMigrationItem $item): void
    {
        if ($item->state !== AccountMigrationItem::STATE_PENDING) {
            return;
        }

        $profile = $migration->profile;
        $data = (array) $item->payload;
        $sourceHash = hash('sha256', $item->source_id);

        $already = Video::where('profile_id', $profile->id)->where('previous_ap_id_hash', $sourceHash)->first();

        if ($already) {
            $item->markDone(
                localId: $already->id,
                targetId: $already->getObjectUrl(),
                targetUrl: $already->shareUrl(),
            );

            return;
        }

        $videoMedia = (array) data_get($data, 'media.video', []);
        $sha256 = $videoMedia['sha256'] ?? null;
        $size = isset($videoMedia['size']) ? (int) $videoMedia['size'] : null;

        if (! is_string($sha256) || strlen($sha256) !== 64 || ! $size) {
            $item->markFailed('Missing media integrity data');

            return;
        }

        if ((int) ($data['duration'] ?? 0) > $this->settings->maxDuration()) {
            $item->markSkipped('over_limits:duration');

            return;
        }

        if ($size > $this->settings->maxVideoBytes()) {
            $item->markSkipped('over_limits:size');

            return;
        }

        $videoUrl = $videoMedia['url'] ?? null;

        if (! is_string($videoUrl)) {
            throw new MigrationException('The source did not provide a media URL', 'media_unavailable');
        }

        $adopted = $migration->remote_profile_id
            ? Video::where('ap_id', $item->source_id)->where('profile_id', $migration->remote_profile_id)->first()
            : null;

        $video = $adopted ?? new Video;
        $isNew = ! $adopted;
        $oldPaths = $adopted ? array_filter([$adopted->vid, $adopted->vid_optimized, $adopted->thumbnail_path]) : [];

        if ($isNew) {
            $video->forceFill([
                'profile_id' => $profile->id,
                'status' => 1,
                'is_local' => true,
                'has_processed' => false,
                'processing_status' => 'processing',
                'visibility' => $this->visibility($data['visibility'] ?? 1),
                'publish_state' => Video::PUBLISH_STATE_PUBLISHED,
                'created_at' => $this->published($data['published'] ?? null),
                'federated_at' => now(),
            ])->save();
        }

        $base = 'videos/'.$profile->id.'/'.$video->id.'/'.Str::random(40);
        $stored = null;
        $thumb = null;

        try {
            $stored = $this->media->streamToStorage($videoUrl, $base.'.720p.mp4', $this->settings->maxVideoBytes(), $sha256, $size, 'video/mp4');

            $thumbMeta = (array) data_get($data, 'media.thumbnail', []);
            $thumbUrl = $thumbMeta['url'] ?? null;

            if (is_string($thumbUrl) && ! empty($thumbMeta['sha256'])) {
                try {
                    $bytes = $this->media->fetchBytes($thumbUrl, (int) config('loops.migration.max_image_mb', 10) * 1024 * 1024, $thumbMeta['sha256']);
                    $thumb = $this->media->storeImage($bytes, $base.'_thumb_'.Str::random(8).'.jpg', 1080, 1920);
                } catch (\Throwable $e) {
                    $thumb = null;
                }
            }
        } catch (\Throwable $e) {
            if ($isNew && $video->exists) {
                $video->forceDelete();
            }

            throw $e;
        }

        $probe = $this->media->inspectStoredVideo($stored['path']);
        $sanitize = app(SanitizeService::class);
        $caption = $this->qualifyMentions($data['caption'] ?? null, $migration->remote_domain);

        $attributes = [
            'profile_id' => $profile->id,
            'is_local' => true,
            'ap_id' => null,
            'uri' => null,
            'remote_media_url' => null,
            'remote_thumb_url' => null,
            'remote_hls_url' => null,
            'previous_ap_id' => $item->source_id,
            'previous_ap_id_hash' => $sourceHash,
            'caption' => $caption !== null ? Str::limit($sanitize->cleanPlainTextWithAllowedLineBreaks($caption) ?? '', 4000, '') : null,
            'visibility' => $this->visibility($data['visibility'] ?? 1),
            'alt_text' => is_string($data['alt_text'] ?? null) ? Str::limit($sanitize->cleanPlainText($data['alt_text']) ?? '', 2000, '') : null,
            'lang' => is_string($data['lang'] ?? null) ? Str::limit($data['lang'], 10, '') : null,
            'is_sensitive' => (bool) ($data['sensitive'] ?? false) || (bool) $profile->enforce_nsfw_label,
            'cw_title' => is_string($data['cw_title'] ?? null) ? Str::limit($sanitize->cleanPlainText($data['cw_title']) ?? '', 191, '') : null,
            'cw_body' => is_string($data['cw_body'] ?? null) ? $sanitize->cleanPlainText($data['cw_body']) : null,
            'contains_ai' => (bool) ($data['contains_ai'] ?? false) || (bool) $profile->enforce_ai_label,
            'contains_ad' => (bool) ($data['contains_ad'] ?? false) || (bool) $profile->enforce_ad_label,
            'comment_state' => max(0, min(9, (int) ($data['comment_state'] ?? 4))),
            'can_download' => (bool) ($data['can_download'] ?? false),
            'can_duet' => (bool) ($data['can_duet'] ?? false),
            'can_stitch' => (bool) ($data['can_stitch'] ?? false),
            'can_embed' => (bool) ($data['can_embed'] ?? true),
            'audio_allow_reuse' => (bool) ($data['audio_allow_reuse'] ?? false),
            'is_pinned' => (bool) ($data['pinned'] ?? false),
            'pinned_order' => isset($data['pinned_order']) ? max(0, min(255, (int) $data['pinned_order'])) : null,
            'category' => is_string($data['category'] ?? null) ? Str::limit($data['category'], 50, '') : null,
            'is_duet' => is_array($data['duet'] ?? null),
            'duet_layout' => (int) data_get($data, 'duet.layout', 0),
            'original_duet_id' => $this->resolveLocalVideoId(data_get($data, 'duet.original')),
            'vid' => $stored['path'],
            'size_kb' => (int) ceil($stored['size'] / 1024),
            'has_thumb' => $thumb !== null,
            'thumbnail_path' => $thumb['path'] ?? null,
            'thumbnail' => null,
            'thumbnail_width' => $thumb['width'] ?? null,
            'thumbnail_height' => $thumb['height'] ?? null,
            'thumbnail_mime' => $thumb ? 'image/jpeg' : null,
            'federated_at' => $video->federated_at ?? now(),
            'ap_published_at' => $this->published($data['published'] ?? null),
            'audio_processed_at' => now(),
            'media_hashes' => array_filter([
                'vid_optimized' => ['path' => $stored['path'], 'sha256' => $stored['sha256'], 'size' => $stored['size']],
                'thumbnail' => $thumb ? ['path' => $thumb['path'], 'sha256' => $thumb['sha256'], 'size' => $thumb['size']] : null,
            ]),
        ];

        if ($probe['ok']) {
            $attributes = array_merge($attributes, [
                'vid_optimized' => $stored['path'],
                'has_processed' => true,
                'processing_status' => 'completed',
                'status' => 2,
                'width' => $probe['width'],
                'height' => $probe['height'],
                'duration' => $probe['duration'],
                'has_audio' => $probe['has_audio'],
            ]);
        } else {
            $attributes = array_merge($attributes, [
                'vid_optimized' => null,
                'has_processed' => false,
                'processing_status' => 'processing',
                'status' => 1,
                'duration' => max(1, (int) ($data['duration'] ?? 1)),
            ]);
            unset($attributes['media_hashes']['vid_optimized']);
        }

        if ($adopted) {
            $attributes['likes'] = VideoLike::where('video_id', $video->id)->count();
            $attributes['shares'] = VideoRepost::where('video_id', $video->id)->count();
        }

        $sound = $this->soundFor($data);

        if ($sound) {
            $attributes['sound_id'] = $sound->id;
        }

        $video->forceFill($attributes)->save();

        if ($video->caption) {
            $video->syncHashtagsFromCaption();
            $video->syncMentionsFromCaption();
        }

        if ($adopted) {
            $video->recalculateCommentsCount();

            foreach (array_diff($oldPaths, [$stored['path'], $thumb['path'] ?? null]) as $path) {
                if (! str_starts_with($path, 'https://')) {
                    Storage::disk('s3')->delete($path);
                }
            }
        }

        if (! $probe['ok']) {
            VideoOptimizeJob::dispatch($video);
        } elseif (! $thumb) {
            VideoThumbnailJob::dispatch($video);
        }

        VideoService::deleteMediaData($video->id);

        $item->markDone(
            localId: $video->id,
            targetId: $video->getObjectUrl(),
            targetUrl: $video->shareUrl(),
            bytes: $stored['size'] + (int) ($thumb['size'] ?? 0),
            error: $probe['ok'] ? null : 'transcoded:'.$probe['reason'],
        );
    }

    public function importRelations(AccountMigration $migration): void
    {
        if ($migration->state !== AccountMigration::STATE_TRANSFERRING || $this->sourceCancelled($migration)) {
            return;
        }

        $this->importSounds($migration);
        $this->importPlaylists($migration);

        $jobs = [];

        foreach ([AccountMigrationItem::KIND_FOLLOW, AccountMigrationItem::KIND_BLOCK, AccountMigrationItem::KIND_BOOKMARK] as $kind) {
            AccountMigrationItem::forMigration($migration)
                ->where('kind', $kind)
                ->where('state', AccountMigrationItem::STATE_PENDING)
                ->orderBy('position')
                ->pluck('id')
                ->chunk(100)
                ->each(function ($ids, $index) use (&$jobs, $migration, $kind) {
                    $jobs[] = new ImportMigrationGraphJob($migration->id, $kind, $ids->values()->all(), (int) $index);
                });
        }

        $migrationId = $migration->id;

        if ($jobs === []) {
            FinalizeInboundMigrationJob::dispatch($migrationId);

            return;
        }

        Bus::batch($jobs)
            ->name('Import migration relations '.$migration->uuid)
            ->allowFailures()
            ->onConnection('redis-migration')
            ->onQueue('migration')
            ->finally(function (Batch $batch) use ($migrationId) {
                FinalizeInboundMigrationJob::dispatch($migrationId);
            })
            ->dispatch();
    }

    public function importSounds(AccountMigration $migration): void
    {
        $profile = $migration->profile;

        AccountMigrationItem::forMigration($migration)
            ->where('kind', AccountMigrationItem::KIND_SOUND)
            ->where('state', AccountMigrationItem::STATE_PENDING)
            ->orderBy('position')
            ->each(function (AccountMigrationItem $item) use ($migration, $profile) {
                try {
                    $data = (array) $item->payload;
                    $hash = $data['fingerprint_hash'] ?? null;

                    if (! is_string($hash) || strlen($hash) > 64 || ! is_string($data['fingerprint'] ?? null)) {
                        $item->markFailed('Invalid sound');

                        return;
                    }

                    $original = $this->resolveLocalVideoId($data['original_video'] ?? null, $profile->id);
                    $sound = VideoSound::where('fingerprint_hash', $hash)->first();
                    $sanitize = app(SanitizeService::class);

                    $details = [
                        'title' => is_string($data['title'] ?? null) ? Str::limit($sanitize->cleanPlainText($data['title']) ?? '', 120, '') : null,
                        'description' => is_string($data['description'] ?? null) ? Str::limit($sanitize->cleanPlainText($data['description']) ?? '', 500, '') : null,
                        'allow_reuse' => (bool) ($data['allow_reuse'] ?? false),
                    ];

                    if ($sound) {
                        $ownedByOldAccount = ! $sound->profile_id || (string) $sound->profile_id === (string) $migration->remote_profile_id;

                        if ($ownedByOldAccount) {
                            $sound->forceFill(array_merge($details, [
                                'profile_id' => $profile->id,
                                'is_original' => true,
                                'original_video_id' => $original ?? $sound->original_video_id,
                            ]))->save();
                        }
                    } else {
                        $sound = new VideoSound;
                        $sound->forceFill(array_merge($details, [
                            'fingerprint' => $data['fingerprint'],
                            'fingerprint_hash' => $hash,
                            'duration' => max(0, min(65535, (int) ($data['duration'] ?? 0))),
                            'status' => (int) ($data['status'] ?? 1),
                            'profile_id' => $profile->id,
                            'original_video_id' => $original,
                            'is_original' => true,
                            'usage_count' => 0,
                        ]))->save();
                    }

                    $videoIds = AccountMigrationItem::forMigration($migration)
                        ->where('kind', AccountMigrationItem::KIND_VIDEO)
                        ->where('state', AccountMigrationItem::STATE_DONE)
                        ->whereNotNull('local_id')
                        ->get(['local_id', 'payload'])
                        ->filter(fn ($row) => data_get($row->payload, 'sound.fingerprint_hash') === $hash)
                        ->pluck('local_id')
                        ->all();

                    if ($videoIds) {
                        Video::whereIn('id', $videoIds)->update(['sound_id' => $sound->id]);
                    }

                    $sound->recalculateUsage();
                    $item->markDone(localId: $sound->id);
                } catch (\Throwable $e) {
                    $item->markFailed($e->getMessage());
                }
            });
    }

    public function importPlaylists(AccountMigration $migration): void
    {
        $profile = $migration->profile;

        AccountMigrationItem::forMigration($migration)
            ->where('kind', AccountMigrationItem::KIND_PLAYLIST)
            ->where('state', AccountMigrationItem::STATE_PENDING)
            ->orderBy('position')
            ->each(function (AccountMigrationItem $item) use ($profile) {
                try {
                    $data = (array) $item->payload;
                    $sanitize = app(SanitizeService::class);
                    $name = $sanitize->cleanPlainTextWithoutLineBreaks((string) ($data['name'] ?? ''));

                    if (! $name) {
                        $item->markSkipped('Playlist has no name');

                        return;
                    }

                    $visibility = in_array($data['visibility'] ?? null, ['public', 'unlisted', 'private', 'followers'], true) ? $data['visibility'] : 'public';

                    $playlist = $profile->playlists()->create([
                        'name' => Str::limit($name, 100, ''),
                        'description' => is_string($data['description'] ?? null) ? Str::limit($sanitize->cleanPlainText($data['description']) ?? '', 500, '') : null,
                        'visibility' => $visibility,
                        'order_column' => isset($data['order']) ? (int) $data['order'] : null,
                    ]);

                    $position = 0;

                    foreach (array_slice((array) ($data['videos'] ?? []), 0, 500) as $url) {
                        $videoId = $this->resolveLocalVideoId($url, $profile->id);

                        if (! $videoId || ! ($video = Video::where('id', $videoId)->where('status', 2)->first())) {
                            continue;
                        }

                        $playlist->addVideo($video, $position++);
                    }

                    $item->markDone(localId: $playlist->id);
                } catch (\Throwable $e) {
                    $item->markFailed($e->getMessage());
                }
            });
    }

    public function importGraph(AccountMigration $migration, string $kind, array $itemIds, int $chunkIndex): void
    {
        if ($migration->state !== AccountMigration::STATE_TRANSFERRING) {
            return;
        }

        $profile = $migration->profile;
        $spacing = max(0, (int) config('loops.migration.follow_spacing_seconds', 1));
        $bookmarkSpacing = max(1, (int) config('loops.migration.bookmark_spacing_seconds', 5));
        $offset = 0;

        AccountMigrationItem::whereIn('id', $itemIds)
            ->where('migration_id', $migration->id)
            ->where('state', AccountMigrationItem::STATE_PENDING)
            ->orderBy('position')
            ->get()
            ->each(function (AccountMigrationItem $item) use ($migration, $profile, $kind, $spacing, $bookmarkSpacing, $chunkIndex, &$offset) {
                try {
                    match ($kind) {
                        AccountMigrationItem::KIND_FOLLOW => $this->importFollow($migration, $profile, $item, ($chunkIndex * 100 + $offset) * $spacing / 10),
                        AccountMigrationItem::KIND_BLOCK => $this->importBlock($migration, $profile, $item),
                        AccountMigrationItem::KIND_BOOKMARK => $this->importBookmark($migration, $profile, $item, ($chunkIndex * 100 + $offset) * $bookmarkSpacing),
                        default => $item->markSkipped('Unsupported item'),
                    };
                } catch (\Throwable $e) {
                    $item->markFailed($e->getMessage());
                }

                $offset++;
            });
    }

    public function importFollow(AccountMigration $migration, Profile $profile, AccountMigrationItem $item, float $delay): void
    {
        $target = $this->resolveProfile($item->source_id);

        if (! $target || (string) $target->id === (string) $profile->id || (string) $target->id === (string) $migration->remote_profile_id || (int) $target->status !== 1) {
            $item->markSkipped('Account unavailable');

            return;
        }

        if (UserFilterService::isBlocking($profile->id, $target->id)) {
            $item->markSkipped('Blocked');

            return;
        }

        if (Follower::where('profile_id', $profile->id)->where('following_id', $target->id)->exists()) {
            $item->markDone(localId: $target->id);

            return;
        }

        if ($target->local) {
            Follower::updateOrCreate([
                'profile_id' => $profile->id,
                'following_id' => $target->id,
            ], [
                'following_is_local' => true,
            ]);
        } else {
            FollowRequest::where('profile_id', $profile->id)->where('following_id', $target->id)->delete();

            $request = FollowRequest::create([
                'profile_id' => $profile->id,
                'following_id' => $target->id,
                'profile_is_local' => true,
                'following_is_local' => false,
                'following_state' => 0,
            ]);

            DeliverFollowRequest::dispatch($request)
                ->onQueue('activitypub-out')
                ->delay(now()->addSeconds((int) $delay));
        }

        $item->markDone(localId: $target->id);
    }

    public function importBlock(AccountMigration $migration, Profile $profile, AccountMigrationItem $item): void
    {
        $target = $this->resolveProfile($item->source_id);

        if (! $target || (string) $target->id === (string) $profile->id) {
            $item->markSkipped('Account unavailable');

            return;
        }

        $filter = DB::transaction(function () use ($profile, $target) {
            $filter = UserFilter::updateOrCreate([
                'profile_id' => $profile->id,
                'account_id' => $target->id,
            ]);

            Follower::where('profile_id', $profile->id)->where('following_id', $target->id)->delete();
            Follower::where('profile_id', $target->id)->where('following_id', $profile->id)->delete();
            FollowRequest::where('profile_id', $profile->id)->where('following_id', $target->id)->delete();
            FollowRequest::where('profile_id', $target->id)->where('following_id', $profile->id)->delete();

            return $filter;
        });

        Cache::forget(UserFilterService::ALL_CACHE_KEY.$profile->id);

        if ($filter->wasRecentlyCreated && ! $target->local && $target->inbox_url) {
            DeliverBlockActivity::dispatch($profile, $target, $filter->id)->onQueue('activitypub-out');
        }

        $item->markDone(localId: $target->id);
    }

    public function importBookmark(AccountMigration $migration, Profile $profile, AccountMigrationItem $item, int $delay): void
    {
        $videoId = $this->resolveLocalVideoId($item->source_id);

        if ($videoId && ($video = Video::where('id', $videoId)->where('status', 2)->first())) {
            $this->bookmark($profile, $video);
            $item->markDone(localId: $video->id);

            return;
        }

        ResolveMigrationBookmarkJob::dispatch($migration->id, $item->id)->delay(now()->addSeconds($delay));
    }

    public function resolveBookmark(AccountMigration $migration, AccountMigrationItem $item, int $attempt): bool
    {
        if ($item->state !== AccountMigrationItem::STATE_PENDING) {
            return true;
        }

        $profile = $migration->profile;
        $videoId = $this->resolveLocalVideoId($item->source_id);

        if ($videoId && ($video = Video::where('id', $videoId)->where('status', 2)->first())) {
            $this->bookmark($profile, $video);
            $item->markDone(localId: $video->id);

            return true;
        }

        if ($attempt === 1) {
            $object = app(ActivityPubService::class)->get($item->source_id);

            if (! is_array($object) || ($object['id'] ?? null) !== $item->source_id || empty($object['attachment'][0]) || ! is_string($object['attributedTo'] ?? null)) {
                $item->markSkipped('Video unavailable');

                return true;
            }

            try {
                app(NoteWithVideoAttachmentValidator::class)->validate($object);
            } catch (\Throwable $e) {
                $item->markSkipped('Video unavailable');

                return true;
            }

            $author = app(Profile::class)->findOrCreateFromUrl($object['attributedTo']);

            if (! $author || $author->local || UserFilterService::isBlocking($profile->id, $author->id)) {
                $item->markSkipped('Video unavailable');

                return true;
            }

            ProcessRemoteVideoJob::dispatch($author->id, $object, $object['attachment'][0])->onQueue('remote-video');
        }

        if ($attempt >= 4) {
            $item->markSkipped('Video unavailable');

            return true;
        }

        return false;
    }

    public function finalize(AccountMigration $migration): void
    {
        $lock = Cache::lock('loops:migration:finalize-in:'.$migration->id, 600);

        if (! $lock->get()) {
            return;
        }

        try {
            $migration->refresh();

            if (! in_array($migration->state, [AccountMigration::STATE_TRANSFERRING, AccountMigration::STATE_SEALING], true)) {
                return;
            }

            if ($this->sourceCancelled($migration)) {
                return;
            }

            $migration->refreshCounters();
            $migration->forceFill(['state' => AccountMigration::STATE_SEALING])->save();

            $leaves = [];
            $count = 0;

            $this->mapQuery($migration)->chunk(500, function ($rows) use (&$leaves, &$count) {
                foreach ($rows as $row) {
                    $leaves[] = $this->attestation->mapLeafHash($row->source_id, $row->target_id);
                    $count++;
                }
            });

            $mapRoot = $this->attestation->merkleRoot($leaves);
            $pending = AccountMigrationItem::forMigration($migration)->where('state', AccountMigrationItem::STATE_PENDING)->count();

            $receipt = $this->attestation->sign([
                'type' => Migration::RECEIPT_TYPE,
                'version' => Migration::VERSION,
                'id' => $migration->local_actor.'#migrations/'.$migration->uuid.'/receipt',
                'intent' => $migration->remote_migration_id,
                'intentHash' => $this->attestation->prefixed(hash('sha256', (string) $migration->intent_jws)),
                'subject' => $migration->remote_actor,
                'target' => $migration->local_actor,
                'source' => $migration->remote_domain,
                'destination' => Migration::host(config('app.url')),
                'manifestRoot' => $this->attestation->prefixed($migration->manifest_root),
                'items' => [
                    'total' => $migration->items_total,
                    'imported' => $migration->items_done,
                    'skipped' => $migration->items_skipped,
                    'failed' => $migration->items_failed,
                    'pending' => $pending,
                ],
                'map' => [
                    'url' => $migration->mapUrl(),
                    'items' => $count,
                    'root' => $this->attestation->prefixed($mapRoot),
                ],
                'issued' => now()->toIso8601ZuluString(),
            ]);

            $seal = null;

            if ($migration->transit_url) {
                $seal = $this->transit->submitReceipt($migration->transit_url, (string) $migration->intent_jws, $receipt);

                if (! $seal && $this->settings->requireSeal()) {
                    throw new MigrationException('The Transit service did not seal the migration', 'transit', 502);
                }
            }

            $migration->forceFill([
                'receipt_jws' => $receipt,
                'map_root' => $mapRoot,
                'seal' => $seal,
                'state' => AccountMigration::STATE_COMPLETED,
                'completed_at' => now(),
            ])->save();

            $remote = $migration->remoteProfile;
            $profile = $migration->profile;

            if ($remote && $remote->inbox_url && $profile) {
                DeliverMigrationActivity::dispatch(
                    $profile->getKeyId(),
                    $remote->inbox_url,
                    MigrationActivityBuilder::accept($migration, $profile)
                );
            }

            if ($profile) {
                AccountService::del($profile->id);
                app(FederationDispatcher::class)->dispatchProfileUpdate($profile->fresh());
            }
        } finally {
            $lock->release();
        }
    }

    public function mapQuery(AccountMigration $migration)
    {
        return AccountMigrationItem::forMigration($migration)
            ->where('kind', AccountMigrationItem::KIND_VIDEO)
            ->where('state', AccountMigrationItem::STATE_DONE)
            ->whereNotNull('target_id')
            ->orderBy('position')
            ->select(['id', 'position', 'source_id', 'target_id', 'target_url']);
    }

    public function qualifyMentions(?string $text, string $domain): ?string
    {
        if ($text === null || $text === '') {
            return $text;
        }

        return preg_replace_callback(
            '/(?<![\w@\/.])@([a-zA-Z0-9_](?:[a-zA-Z0-9_.-]*[a-zA-Z0-9_])?)(?!@|[a-zA-Z0-9_-]|\.[a-zA-Z0-9_])/u',
            fn ($match) => '@'.$match[1].'@'.$domain,
            $text
        );
    }

    public function resolveLocalVideoId($url, $profileId = null): ?int
    {
        if (! is_string($url) || $url === '') {
            return null;
        }

        $sanitize = app(SanitizeService::class);

        if ($sanitize->isLocalObject($url)) {
            $match = $sanitize->matchUrlTemplate(
                url: $url,
                templates: ['/ap/users/{profileId}/video/{videoId}'],
                useAppHost: true,
                constraints: ['profileId' => '\d+', 'videoId' => '\d+']
            );

            if (! $match) {
                return null;
            }

            $query = Video::where('id', $match['videoId']);

            if ($profileId) {
                $query->where('profile_id', $profileId);
            }

            return $query->value('id');
        }

        $query = Video::where('previous_ap_id_hash', hash('sha256', $url));

        if ($profileId) {
            $query->where('profile_id', $profileId);
        }

        $id = $query->value('id');

        if ($id || $profileId) {
            return $id ? (int) $id : null;
        }

        $id = Video::where('ap_id', $url)->value('id');

        return $id ? (int) $id : null;
    }

    protected function resolveProfile(string $uri): ?Profile
    {
        try {
            return app(Profile::class)->findOrCreateFromUrl($uri);
        } catch (\Throwable $e) {
            return null;
        }
    }

    protected function bookmark(Profile $profile, Video $video): void
    {
        $bookmark = VideoBookmark::firstOrCreate(['profile_id' => $profile->id, 'video_id' => $video->id]);

        if ($bookmark->wasRecentlyCreated) {
            $video->increment('bookmarks');
        }
    }

    protected function soundFor(array $data): ?VideoSound
    {
        $hash = data_get($data, 'sound.fingerprint_hash');

        if (! is_string($hash) || data_get($data, 'sound.original')) {
            return null;
        }

        return VideoSound::where('fingerprint_hash', $hash)->first();
    }

    protected function validEntry($entry, int $position): bool
    {
        return is_array($entry)
            && ($entry['position'] ?? null) === $position
            && in_array($entry['kind'] ?? null, self::KINDS, true)
            && is_string($entry['id'] ?? null)
            && strlen($entry['id']) <= 2048
            && str_starts_with($entry['id'], 'https://')
            && is_array($entry['data'] ?? null);
    }

    protected function visibility($value): int
    {
        $value = (int) $value;

        return in_array($value, [1, 2, 3, 4, 5], true) ? $value : 1;
    }

    protected function published($value): Carbon
    {
        try {
            $date = is_string($value) ? Carbon::parse($value) : now();
        } catch (\Throwable $e) {
            $date = now();
        }

        return $date->isFuture() ? now() : $date;
    }
}
