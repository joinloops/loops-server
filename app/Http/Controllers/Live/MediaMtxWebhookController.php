<?php

namespace App\Http\Controllers\Live;

use App\Http\Controllers\Controller;
use App\Models\LiveStream;
use App\Services\Live\LiveStreamService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class MediaMtxWebhookController extends Controller
{
    public function __construct(protected LiveStreamService $service) {}

    public function auth(Request $request)
    {
        $action = (string) $request->input('action');
        $path = (string) $request->input('path');

        if (in_array($action, ['api', 'metrics', 'pprof'], true)) {
            return $this->deny();
        }

        $channel = $this->service->channelByPath($path);

        if (! $channel) {
            return $this->deny();
        }

        if ($action === 'read' || $action === 'playback') {
            return $channel->visibility === 1 && $channel->is_enabled
                ? $this->allow()
                : $this->deny();
        }

        if ($action !== 'publish') {
            return $this->deny();
        }

        if (! $this->service->verifyKey($channel, $request->input('password'))) {
            Log::info('live.publish_rejected', ['reason' => 'key', 'path' => $path]);

            return $this->deny();
        }

        if ((string) $request->input('user') !== (string) config('live.rtmp.user')) {
            return $this->deny();
        }

        if (! $this->service->canGoLive($channel)) {
            Log::info('live.publish_rejected', ['reason' => 'ineligible', 'path' => $path]);

            return $this->deny();
        }

        return $this->allow();
    }

    public function ready(Request $request)
    {
        abort_unless($this->verifySecret($request), 403);

        $channel = $this->service->channelByPath((string) $request->input('path'));

        if (! $channel) {
            return $this->allow();
        }

        $stream = $this->service->beginStream($channel, $request->input('source_id'));

        Log::info('live.started', ['stream_id' => $stream->id, 'channel_id' => $channel->id]);

        return $this->allow();
    }

    public function notReady(Request $request)
    {
        abort_unless($this->verifySecret($request), 403);

        $channel = $this->service->channelByPath((string) $request->input('path'));

        if (! $channel) {
            return $this->allow();
        }

        $stream = $this->service->currentStream($channel);

        if ($stream) {
            $this->service->endStream($stream, LiveStream::STATUS_ENDED);
            Log::info('live.ended', ['stream_id' => $stream->id]);
        }

        return $this->allow();
    }

    protected function verifySecret(Request $request): bool
    {
        $expected = (string) config('live.mediamtx.webhook_secret');

        if ($expected === '') {
            return false;
        }

        return hash_equals($expected, (string) $request->header('X-Live-Secret'));
    }

    protected function allow(): Response
    {
        return response()->noContent();
    }

    protected function deny(): Response
    {
        return response()->noContent(401);
    }
}
