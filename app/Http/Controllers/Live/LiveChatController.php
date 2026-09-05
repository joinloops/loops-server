<?php

namespace App\Http\Controllers\Live;

use App\Events\Live\ViewerBanned;
use App\Http\Controllers\Controller;
use App\Models\LiveChannelBan;
use App\Models\LiveStream;
use App\Services\AccountService;
use App\Services\Live\LiveChatService;
use App\Services\Live\LiveStreamService;
use App\Services\Live\LiveViewerService;
use Illuminate\Http\Request;

class LiveChatController extends Controller
{
    public function __construct(
        protected LiveStreamService $streams,
        protected LiveChatService $chat,
        protected LiveViewerService $viewers
    ) {
        $this->middleware('auth');
    }

    public function show(Request $request, string $publicId)
    {
        $stream = $this->resolveLiveStream($publicId);

        return response()->json([
            'stream' => $this->streamPayload($stream),
            'messages' => $this->chat->buffer($stream),
            'viewers' => $this->viewers->count($stream),
        ]);
    }

    public function store(Request $request, string $publicId)
    {
        $request->validate([
            'body' => 'required|string',
        ]);

        $stream = $this->resolveLiveStream($publicId);
        $profile = $request->user()->profile;

        abort_if(! $profile, 403);

        $message = $this->chat->send($stream, $profile, $request->input('body'));

        return response()->json($message, 201);
    }

    public function destroy(Request $request, string $publicId, int $seq)
    {
        $stream = $this->resolveLiveStream($publicId);
        $profile = $request->user()->profile;

        abort_unless($profile && $this->chat->isModerator($stream, $profile), 403);

        $this->chat->delete($stream, $seq);

        return response()->json(['deleted' => true]);
    }

    public function ban(Request $request, string $publicId)
    {
        $request->validate([
            'profile_id' => 'required|integer',
        ]);

        $stream = $this->resolveLiveStream($publicId);
        $profile = $request->user()->profile;

        abort_unless($profile && $this->chat->isModerator($stream, $profile), 403);

        $targetId = (int) $request->input('profile_id');

        abort_if($targetId === (int) $stream->profile_id, 422);

        LiveChannelBan::updateOrCreate(
            ['live_channel_id' => $stream->live_channel_id, 'profile_id' => $targetId],
            ['moderator_id' => $profile->id, 'expires_at' => null]
        );

        broadcast(new ViewerBanned($stream->channel->public_id, $targetId));

        return response()->json(['banned' => true]);
    }

    public function heartbeat(Request $request, string $publicId)
    {
        $stream = $this->resolveLiveStream($publicId);
        $profileId = optional($request->user())->profile_id;

        $token = $this->viewers->heartbeat(
            $stream,
            $profileId,
            $request->input('guest_token')
        );

        return response()->json([
            'token' => $token,
            'viewers' => $this->viewers->count($stream),
            'status' => $stream->status,
        ]);
    }

    protected function resolveLiveStream(string $publicId): LiveStream
    {
        $channel = $this->streams->channelByPath(
            trim((string) config('live.rtmp.app'), '/').'/'.$publicId
        );

        abort_if(! $channel || ! $channel->is_enabled, 404);

        $stream = $this->streams->currentStream($channel);

        abort_if(! $stream, 404);

        $stream->load('profile');
        $stream->setRelation('channel', $channel);

        return $stream;
    }

    protected function streamPayload(LiveStream $stream): array
    {
        $account = AccountService::get($stream->profile_id);

        return [
            'id' => (string) $stream->id,
            'public_id' => $stream->channel->public_id,
            'status' => $stream->status,
            'title' => $stream->title,
            'started_at' => optional($stream->started_at)->toISOString(),
            'chat_enabled' => (bool) $stream->channel->chat_enabled,
            'chat_mode' => $stream->channel->chat_mode,
            'playback_url' => $this->streams->playbackUrl($stream->channel),
            'realtime' => $this->streams->realtimeConfig(),
            'host' => $account ? [
                'id' => (string) $stream->profile_id,
                'username' => $account['username'] ?? 'username',
                'name' => $account['name'] ?? 'name',
                'avatar' => $account['avatar'] ?? url('/storage/avatars/default.jpg'),
            ] : null,
        ];
    }
}
