<?php

namespace App\Http\Controllers\Live;

use App\Http\Controllers\Controller;
use App\Models\LiveStream;
use App\Services\Live\LiveChatService;
use App\Services\Live\LiveStreamService;
use Illuminate\Http\Request;

class LiveChannelController extends Controller
{
    public function __construct(
        protected LiveStreamService $streams,
        protected LiveChatService $chat
    ) {
        $this->middleware('auth');
    }

    public function show(Request $request)
    {
        $profile = $request->user()->profile;

        abort_if(! $profile, 403);

        $channel = $this->streams->channelFor($profile);
        $current = $this->streams->currentStream($channel);

        return response()->json([
            'channel' => [
                'public_id' => $channel->public_id,
                'title' => $channel->title,
                'description' => $channel->description,
                'visibility' => $channel->visibility,
                'chat_enabled' => (bool) $channel->chat_enabled,
                'chat_mode' => $channel->chat_mode,
                'is_enabled' => (bool) $channel->is_enabled,
                'can_go_live' => $this->streams->canGoLive($channel),
            ],
            'ingest' => $this->streams->ingestFor($channel),
            'realtime' => $this->streams->realtimeConfig(),
            'playback_url' => $this->streams->playbackUrl($channel),
            'current_stream' => $current ? [
                'id' => (string) $current->id,
                'status' => $current->status,
                'started_at' => optional($current->started_at)->toISOString(),
            ] : null,
        ]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'title' => 'sometimes|nullable|string|max:120',
            'description' => 'sometimes|nullable|string|max:500',
            'chat_enabled' => 'sometimes|boolean',
            'chat_mode' => 'sometimes|in:everyone,followers,mutuals',
            'visibility' => 'sometimes|integer|in:0,1',
        ]);

        $profile = $request->user()->profile;

        abort_if(! $profile, 403);

        $channel = $this->streams->channelFor($profile);
        $channel->update($data);
        $this->streams->forgetChannel($channel);

        if ($current = $this->streams->currentStream($channel)) {
            if (array_key_exists('title', $data)) {
                $current->update(['title' => $data['title']]);
            }
        }

        return $this->show($request);
    }

    public function rotateKey(Request $request)
    {
        $profile = $request->user()->profile;

        abort_if(! $profile, 403);

        $channel = $this->streams->channelFor($profile);
        $this->streams->rotateKey($channel);

        return $this->show($request);
    }

    public function end(Request $request)
    {
        $profile = $request->user()->profile;

        abort_if(! $profile, 403);

        $channel = $this->streams->channelFor($profile);
        $current = $this->streams->currentStream($channel);

        abort_if(! $current, 404);

        $this->streams->drop($current);
        $this->streams->endStream($current, LiveStream::STATUS_ENDED);

        return response()->json(['ended' => true]);
    }
}
