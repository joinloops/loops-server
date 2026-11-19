<?php

namespace App\Services\Migration;

use App\Models\AccountMigration;
use App\Models\AccountMigrationItem;
use App\Models\Follower;
use App\Models\Playlist;
use App\Models\Profile;
use App\Models\UserFilter;
use App\Models\Video;
use App\Models\VideoBookmark;
use App\Models\VideoSound;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ManifestBuilder
{
    protected int $position = 0;

    protected array $leaves = [];

    protected array $buffer = [];

    protected int $bytes = 0;

    protected ?AccountMigration $migration = null;

    public function __construct(
        protected AttestationService $attestation,
        protected MediaHashService $hashes,
    ) {}

    public function build(AccountMigration $migration): void
    {
        $this->migration = $migration;
        $this->position = 0;
        $this->leaves = [];
        $this->buffer = [];
        $this->bytes = 0;

        AccountMigrationItem::forMigration($migration)->delete();

        $profile = $migration->profile;

        if ($migration->hasScope('profile')) {
            $this->addProfile($profile);
        }

        if ($migration->hasScope('videos')) {
            $this->addVideos($profile);
        }

        if ($migration->hasScope('sounds')) {
            $this->addSounds($profile);
        }

        if ($migration->hasScope('playlists')) {
            $this->addPlaylists($profile);
        }

        if ($migration->hasScope('following')) {
            $this->addFollowing($profile);
        }

        if ($migration->hasScope('blocks')) {
            $this->addBlocks($profile);
        }

        if ($migration->hasScope('bookmarks')) {
            $this->addBookmarks($profile);
        }

        $this->flush();

        $migration->forceFill([
            'manifest_root' => $this->attestation->merkleRoot($this->leaves),
            'manifest_items' => $this->position,
            'manifest_bytes' => $this->bytes,
            'items_total' => $this->position,
        ])->save();
    }

    public function backfillHashes(AccountMigration $migration, float $deadline): bool
    {
        if (! $migration->hasScope('videos')) {
            return true;
        }

        $complete = true;

        $this->videoQuery($migration->profile)->chunkById(50, function ($videos) use ($deadline, &$complete) {
            foreach ($videos as $video) {
                if (microtime(true) >= $deadline) {
                    $complete = false;

                    return false;
                }

                $this->hashes->forVideo($video);
            }
        });

        return $complete;
    }

    public function profileData(Profile $profile): array
    {
        $avatar = null;
        $avatarPath = $this->storagePathFromUrl($profile->avatar);

        if ($avatarPath && ($hash = $this->hashes->hashStored($avatarPath))) {
            $avatar = [
                'url' => Storage::disk('s3')->url($avatarPath),
                'sha256' => $hash['sha256'],
                'size' => $hash['size'],
            ];
        }

        return [
            'username' => $profile->username,
            'name' => $profile->name,
            'bio' => $profile->bio,
            'avatar' => $avatar,
            'links' => $profile->profileLinks()->orderBy('position')->get()->map(fn ($link) => [
                'url' => $link->url,
                'title' => $link->title,
            ])->values()->all(),
        ];
    }

    public function videoData(Video $video, array $hashes, Profile $profile): array
    {
        $sound = $video->sound_id ? VideoSound::find($video->sound_id) : null;
        $originalDuet = $video->is_duet && $video->original_duet_id ? Video::find($video->original_duet_id) : null;

        return [
            'url' => $video->shareUrl(),
            'published' => $video->created_at?->toIso8601ZuluString(),
            'caption' => $video->caption,
            'visibility' => (int) ($video->visibility ?? 1),
            'alt_text' => $video->alt_text,
            'lang' => $video->lang,
            'sensitive' => (bool) $video->is_sensitive,
            'cw_title' => $video->cw_title,
            'cw_body' => $video->cw_body,
            'contains_ai' => (bool) $video->contains_ai,
            'contains_ad' => (bool) $video->contains_ad,
            'comment_state' => (int) $video->comment_state,
            'can_download' => (bool) $video->can_download,
            'can_duet' => (bool) $video->can_duet,
            'can_stitch' => (bool) $video->can_stitch,
            'can_embed' => (bool) ($video->can_embed ?? true),
            'audio_allow_reuse' => (bool) $video->audio_allow_reuse,
            'pinned' => (bool) $video->is_pinned,
            'pinned_order' => $video->pinned_order !== null ? (int) $video->pinned_order : null,
            'category' => $video->category,
            'duration' => (int) $video->duration,
            'width' => (int) $video->width,
            'height' => (int) $video->height,
            'has_audio' => (bool) $video->has_audio,
            'duet' => $video->is_duet ? [
                'original' => $originalDuet?->getObjectUrl(),
                'layout' => (int) ($video->duet_layout ?? 0),
            ] : null,
            'sound' => $sound ? [
                'fingerprint_hash' => $sound->fingerprint_hash,
                'original' => (bool) $sound->is_original && (string) $sound->profile_id === (string) $profile->id,
            ] : null,
            'media' => [
                'video' => [
                    'url' => Storage::disk('s3')->url($hashes['vid_optimized']['path']),
                    'sha256' => $hashes['vid_optimized']['sha256'],
                    'size' => (int) $hashes['vid_optimized']['size'],
                    'mime' => 'video/mp4',
                ],
                'thumbnail' => isset($hashes['thumbnail']['sha256'], $hashes['thumbnail']['path']) ? [
                    'url' => Storage::disk('s3')->url($hashes['thumbnail']['path']),
                    'sha256' => $hashes['thumbnail']['sha256'],
                    'size' => (int) $hashes['thumbnail']['size'],
                    'mime' => $this->imageMime($hashes['thumbnail']['path'] ?? ''),
                ] : null,
            ],
        ];
    }

    public function storagePathFromUrl(?string $url): ?string
    {
        if (! $url) {
            return null;
        }

        $base = rtrim(Storage::disk('s3')->url(''), '/').'/';

        if (! str_starts_with($url, $base)) {
            return null;
        }

        $path = Str::after($url, $base);

        return $path !== '' ? strtok($path, '?') : null;
    }

    protected function addProfile(Profile $profile): void
    {
        $data = $this->profileData($profile);
        $this->push(AccountMigrationItem::KIND_PROFILE, $profile->getActorId(), $data, (int) $profile->id, (int) ($data['avatar']['size'] ?? 0));
    }

    protected function videoQuery(Profile $profile)
    {
        return Video::where('profile_id', $profile->id)
            ->where('status', 2)
            ->where('is_local', true)
            ->where('has_processed', true)
            ->whereNotNull('vid_optimized')
            ->orderBy('id');
    }

    protected function addVideos(Profile $profile): void
    {
        $this->videoQuery($profile)
            ->chunkById(100, function ($videos) use ($profile) {
                foreach ($videos as $video) {
                    $hashes = $this->hashes->forVideo($video);

                    if (empty($hashes['vid_optimized']['sha256'])) {
                        continue;
                    }

                    $data = $this->videoData($video, $hashes, $profile);
                    $bytes = (int) $hashes['vid_optimized']['size'] + (int) ($hashes['thumbnail']['size'] ?? 0);

                    $this->push(AccountMigrationItem::KIND_VIDEO, $video->getObjectUrl(), $data, (int) $video->id, $bytes);
                }
            });
    }

    protected function addSounds(Profile $profile): void
    {
        VideoSound::where('profile_id', $profile->id)
            ->where('is_original', true)
            ->orderBy('id')
            ->chunkById(100, function ($sounds) {
                foreach ($sounds as $sound) {
                    $original = $sound->original_video_id ? Video::find($sound->original_video_id) : null;

                    $this->push(AccountMigrationItem::KIND_SOUND, url('/ap/sounds/'.$sound->fingerprint_hash), [
                        'fingerprint_hash' => $sound->fingerprint_hash,
                        'fingerprint' => $sound->fingerprint,
                        'title' => $sound->title,
                        'description' => $sound->description,
                        'duration' => (int) $sound->duration,
                        'allow_reuse' => (bool) $sound->allow_reuse,
                        'status' => (int) $sound->status,
                        'original_video' => $original?->getObjectUrl(),
                    ], (int) $sound->id);
                }
            });
    }

    protected function addPlaylists(Profile $profile): void
    {
        Playlist::where('profile_id', $profile->id)
            ->orderBy('id')
            ->chunkById(50, function ($playlists) use ($profile) {
                foreach ($playlists as $playlist) {
                    $videos = DB::table('playlist_video')
                        ->join('videos', 'videos.id', '=', 'playlist_video.video_id')
                        ->where('playlist_video.playlist_id', $playlist->id)
                        ->where('videos.profile_id', $profile->id)
                        ->where('videos.status', 2)
                        ->orderBy('playlist_video.position')
                        ->pluck('videos.id')
                        ->map(fn ($id) => url('/ap/users/'.$profile->id.'/video/'.$id))
                        ->values()
                        ->all();

                    $this->push(AccountMigrationItem::KIND_PLAYLIST, url('/ap/playlists/'.$playlist->id), [
                        'name' => $playlist->name,
                        'description' => $playlist->description,
                        'visibility' => $playlist->visibility,
                        'order' => (int) $playlist->order_column,
                        'videos' => $videos,
                    ], (int) $playlist->id);
                }
            });
    }

    protected function addFollowing(Profile $profile): void
    {
        $limit = (int) config('loops.migration.max_follows', 7500);
        $count = 0;

        Follower::where('profile_id', $profile->id)
            ->join('profiles', 'profiles.id', '=', 'followers.following_id')
            ->where('profiles.status', 1)
            ->select('followers.id', 'followers.following_id')
            ->orderBy('followers.id')
            ->chunkById(500, function ($rows) use (&$count, $limit) {
                $profiles = Profile::whereIn('id', $rows->pluck('following_id'))->get()->keyBy('id');

                foreach ($rows as $row) {
                    if ($count >= $limit) {
                        return false;
                    }

                    $target = $profiles->get($row->following_id);

                    if (! $target || (! $target->local && ! $target->uri)) {
                        continue;
                    }

                    $this->push(AccountMigrationItem::KIND_FOLLOW, $target->getActorId(), [], (int) $target->id);
                    $count++;
                }
            }, 'followers.id', 'id');
    }

    protected function addBlocks(Profile $profile): void
    {
        $limit = (int) config('loops.migration.max_blocks', 1000);

        UserFilter::where('profile_id', $profile->id)
            ->orderBy('id')
            ->limit($limit)
            ->get()
            ->chunk(500)
            ->each(function ($rows) {
                $profiles = Profile::whereIn('id', $rows->pluck('account_id'))->get()->keyBy('id');

                foreach ($rows as $row) {
                    $target = $profiles->get($row->account_id);

                    if (! $target || (! $target->local && ! $target->uri)) {
                        continue;
                    }

                    $this->push(AccountMigrationItem::KIND_BLOCK, $target->getActorId(), [], (int) $target->id);
                }
            });
    }

    protected function addBookmarks(Profile $profile): void
    {
        $limit = (int) config('loops.migration.max_bookmarks', 2000);

        VideoBookmark::where('profile_id', $profile->id)
            ->orderBy('id')
            ->limit($limit)
            ->get()
            ->chunk(500)
            ->each(function ($rows) {
                $videos = Video::whereIn('id', $rows->pluck('video_id'))->where('status', 2)->get()->keyBy('id');

                foreach ($rows as $row) {
                    $video = $videos->get($row->video_id);

                    if (! $video || (! $video->is_local && ! $video->ap_id)) {
                        continue;
                    }

                    $this->push(AccountMigrationItem::KIND_BOOKMARK, $video->is_local ? $video->getObjectUrl() : $video->ap_id, [
                        'bookmarked' => $row->created_at?->toIso8601ZuluString(),
                    ], (int) $video->id);
                }
            });
    }

    protected function push(string $kind, string $id, array $data, ?int $localId = null, int $bytes = 0): void
    {
        $this->position++;
        $leaf = $this->attestation->leafHash($this->position, $kind, $id, $data);
        $this->leaves[] = $leaf;
        $this->bytes += $bytes;

        $now = now();

        $this->buffer[] = [
            'migration_id' => $this->migration->id,
            'position' => $this->position,
            'kind' => $kind,
            'source_id' => $id,
            'source_hash' => hash('sha256', $id),
            'local_id' => $localId,
            'payload' => json_encode($data, AttestationService::JSON_FLAGS),
            'leaf_hash' => $leaf,
            'state' => AccountMigrationItem::STATE_PENDING,
            'bytes' => $bytes,
            'created_at' => $now,
            'updated_at' => $now,
        ];

        if (count($this->buffer) >= 200) {
            $this->flush();
        }
    }

    protected function flush(): void
    {
        if ($this->buffer === []) {
            return;
        }

        AccountMigrationItem::insert($this->buffer);
        $this->buffer = [];
    }

    protected function imageMime(string $path): string
    {
        return match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
            'webp' => 'image/webp',
            'png' => 'image/png',
            default => 'image/jpeg',
        };
    }
}
