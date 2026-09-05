<?php

namespace App\Services\Live;

use App\Contracts\LiveProvider;
use App\Events\Live\StreamStateChanged;
use App\Models\LiveChannel;
use App\Models\LiveChannelBan;
use App\Models\LiveStream;
use App\Models\Profile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;

class LiveStreamService
{
    protected const CACHE_PREFIX = 'loops:live:';

    public function __construct(protected LiveProvider $provider) {}

    public function channelFor(Profile $profile): LiveChannel
    {
        $channel = LiveChannel::firstOrCreate(
            ['profile_id' => $profile->id],
            [
                'public_id' => (string) Str::uuid(),
                'stream_key' => LiveChannel::newStreamKey(),
            ]
        );

        if ($channel->wasRecentlyCreated) {
            $this->forgetChannel($channel);
        }

        return $channel;
    }

    public function channelByPath(string $path): ?LiveChannel
    {
        $publicId = $this->publicIdFromPath($path);

        if (! $publicId) {
            return null;
        }

        return Cache::remember(
            self::CACHE_PREFIX.'channel:'.$publicId,
            300,
            fn () => LiveChannel::where('public_id', $publicId)->first()
        );
    }

    public function publicIdFromPath(string $path): ?string
    {
        $app = trim((string) config('live.rtmp.app'), '/');
        $path = trim($path, '/');

        if ($app !== '') {
            if (! str_starts_with($path, $app.'/')) {
                return null;
            }
            $path = substr($path, strlen($app) + 1);
        }

        if (! preg_match('/^[0-9a-fA-F-]{36}$/', $path)) {
            return null;
        }

        return $path;
    }

    public function verifyKey(LiveChannel $channel, ?string $key): bool
    {
        if (! is_string($key) || $key === '') {
            return false;
        }

        return hash_equals($channel->stream_key, $key);
    }

    public function canGoLive(LiveChannel $channel): bool
    {
        if (! config('live.enabled') || ! $channel->is_enabled) {
            return false;
        }

        $profile = $channel->profile;

        if (! $profile) {
            return false;
        }

        if ($profile->status != 1) {
            return false;
        }

        $minAge = (int) config('live.eligibility.min_account_age_days');
        $minFollowers = (int) config('live.eligibility.min_followers');

        if ($minAge > 0 && $profile->created_at->gt(now()->subDays($minAge))) {
            return false;
        }

        if ($minFollowers > 0 && (int) $profile->followers < $minFollowers) {
            return false;
        }

        return true;
    }

    public function isBanned(LiveChannel $channel, int $profileId): bool
    {
        $ban = LiveChannelBan::where('live_channel_id', $channel->id)
            ->where('profile_id', $profileId)
            ->first();

        return $ban !== null && $ban->isActive();
    }

    public function beginStream(LiveChannel $channel, ?string $sourceId = null): LiveStream
    {
        return DB::transaction(function () use ($channel, $sourceId) {
            $this->closeOpenStreams($channel, LiveStream::STATUS_FAILED);

            $stream = LiveStream::create([
                'live_channel_id' => $channel->id,
                'profile_id' => $channel->profile_id,
                'status' => LiveStream::STATUS_LIVE,
                'title' => $channel->title,
                'visibility' => $channel->visibility,
                'source_id' => $sourceId,
                'started_at' => now(),
                'last_seen_at' => now(),
            ]);

            $channel->update(['current_stream_id' => $stream->id]);
            $this->forgetChannel($channel);

            broadcast(new StreamStateChanged(
                $channel->public_id,
                $stream->id,
                LiveStream::STATUS_LIVE
            ));

            return $stream;
        });
    }

    public function endStream(LiveStream $stream, string $status = LiveStream::STATUS_ENDED): LiveStream
    {
        if ($stream->isOver()) {
            return $stream;
        }

        $viewers = app(LiveViewerService::class);

        $stream->update([
            'status' => $status,
            'ended_at' => now(),
            'peak_viewers' => max((int) $stream->peak_viewers, $viewers->peak($stream)),
            'total_viewers' => max((int) $stream->total_viewers, $viewers->uniqueTotal($stream)),
        ]);

        if ($stream->channel && (int) $stream->channel->current_stream_id === (int) $stream->id) {
            $stream->channel->update(['current_stream_id' => null]);
            $this->forgetChannel($stream->channel);
        }

        if ($stream->channel) {
            broadcast(new StreamStateChanged(
                $stream->channel->public_id,
                $stream->id,
                $status
            ));
        }

        $this->flushRuntimeKeys($stream);

        return $stream->refresh();
    }

    public function touch(LiveStream $stream): void
    {
        $stream->forceFill(['last_seen_at' => now()])->saveQuietly();
    }

    public function currentStream(LiveChannel $channel): ?LiveStream
    {
        if (! $channel->current_stream_id) {
            return null;
        }

        return LiveStream::find($channel->current_stream_id);
    }

    public function ingestFor(LiveChannel $channel): array
    {
        return $this->provider->ingestFor($channel);
    }

    public function playbackUrl(LiveChannel $channel): string
    {
        return $this->provider->playbackUrl($channel);
    }

    public function drop(LiveStream $stream): bool
    {
        return $this->provider->drop($stream);
    }

    public function rotateKey(LiveChannel $channel): LiveChannel
    {
        $channel->update([
            'stream_key' => LiveChannel::newStreamKey(),
            'key_rotated_at' => now(),
        ]);

        $this->forgetChannel($channel);

        if ($current = $this->currentStream($channel)) {
            $this->drop($current);
        }

        return $channel->refresh();
    }

    public function realtimeConfig(): array
    {
        return [
            'ws_host' => config('reverb.apps.apps.0.options.host'),
            'ws_port' => (int) config('reverb.apps.apps.0.options.port', 443),
            'scheme' => config('reverb.apps.apps.0.options.scheme', 'https'),
            'app_key' => config('reverb.apps.apps.0.key'),
        ];
    }

    public function forgetChannel(LiveChannel $channel): void
    {
        Cache::forget(self::CACHE_PREFIX.'channel:'.$channel->public_id);
    }

    protected function closeOpenStreams(LiveChannel $channel, string $status): void
    {
        LiveStream::where('live_channel_id', $channel->id)
            ->active()
            ->get()
            ->each(fn (LiveStream $stream) => $this->endStream($stream, $status));
    }

    protected function flushRuntimeKeys(LiveStream $stream): void
    {
        foreach (['chat', 'viewers', 'peak', 'uniq', 'seq'] as $suffix) {
            Redis::del(self::CACHE_PREFIX.$stream->id.':'.$suffix);
        }
    }
}
