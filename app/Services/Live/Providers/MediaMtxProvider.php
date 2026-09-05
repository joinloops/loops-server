<?php

namespace App\Services\Live\Providers;

use App\Contracts\LiveProvider;
use App\Models\LiveChannel;
use App\Models\LiveStream;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MediaMtxProvider implements LiveProvider
{
    public function ingestFor(LiveChannel $channel): array
    {
        $host = config('live.rtmp.host');
        $port = config('live.rtmp.port');
        $app = trim((string) config('live.rtmp.app'), '/');
        $user = config('live.rtmp.user');

        $base = $app === ''
            ? sprintf('rtmp://%s:%s/', $host, $port)
            : sprintf('rtmp://%s:%s/%s/', $host, $port, $app);

        $key = sprintf(
            '%s?user=%s&pass=%s',
            $channel->public_id,
            rawurlencode((string) $user),
            rawurlencode($channel->stream_key)
        );

        return ['url' => $base, 'key' => $key];
    }

    public function playbackUrl(LiveChannel $channel): string
    {
        return sprintf(
            '%s/%s/index.m3u8',
            rtrim((string) config('live.playback.base'), '/'),
            $channel->ingestPath()
        );
    }

    public function isPublishing(LiveChannel $channel): bool
    {
        $path = $this->path($channel->ingestPath());

        return (bool) data_get($path, 'ready', false);
    }

    public function activePaths(): array
    {
        $response = $this->api()->get('/v3/paths/list', ['itemsPerPage' => 500]);

        if (! $response->successful()) {
            return [];
        }

        return collect($response->json('items', []))
            ->filter(fn ($item) => data_get($item, 'ready') === true)
            ->pluck('name')
            ->all();
    }

    public function drop(LiveStream $stream): bool
    {
        $channel = $stream->channel;

        if (! $channel) {
            return false;
        }

        $path = $this->path($channel->ingestPath());
        $connId = data_get($path, 'source.id');

        if (! $connId) {
            return false;
        }

        $response = $this->api()->post("/v3/rtmpconns/kick/{$connId}");

        if (! $response->successful()) {
            Log::warning('live.drop_failed', [
                'stream_id' => $stream->id,
                'status' => $response->status(),
            ]);
        }

        return $response->successful();
    }

    protected function path(string $name): ?array
    {
        $response = $this->api()->get('/v3/paths/get/'.rawurlencode($name));

        return $response->successful() ? $response->json() : null;
    }

    protected function api()
    {
        return Http::baseUrl(rtrim((string) config('live.mediamtx.api'), '/'))
            ->timeout(3)
            ->acceptJson();
    }
}
