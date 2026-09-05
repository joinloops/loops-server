<?php

namespace App\Services\Live;

use App\Events\Live\ChatMessageDeleted;
use App\Events\Live\ChatMessageSent;
use App\Jobs\Live\PersistLiveMessage;
use App\Models\Follower;
use App\Models\LiveChannelModerator;
use App\Models\LiveStream;
use App\Models\LiveStreamMessage;
use App\Models\Profile;
use Illuminate\Support\Facades\Redis;
use Illuminate\Validation\ValidationException;

class LiveChatService
{
    protected const PREFIX = 'loops:live:';

    public function __construct(protected LiveStreamService $streams) {}

    public function send(LiveStream $stream, Profile $profile, string $body): array
    {
        $body = trim($body);
        $max = (int) config('live.chat.max_length');

        if ($body === '' || mb_strlen($body) > $max) {
            throw ValidationException::withMessages([
                'body' => "Messages must be between 1 and {$max} characters.",
            ]);
        }

        $this->assertCanSend($stream, $profile);

        $seq = (int) Redis::incr(self::PREFIX.$stream->id.':seq');

        $message = [
            'seq' => $seq,
            'type' => LiveStreamMessage::TYPE_MESSAGE,
            'body' => $body,
            'created_at' => now()->toISOString(),
            'profile' => $this->profilePayload($profile),
        ];

        $this->pushToBuffer($stream, $message);

        broadcast(new ChatMessageSent($stream->channel->public_id, $message));

        PersistLiveMessage::dispatch($stream->id, $profile->id, $seq, $body)
            ->onQueue('livechat');

        return $message;
    }

    public function system(LiveStream $stream, string $body): array
    {
        $seq = (int) Redis::incr(self::PREFIX.$stream->id.':seq');

        $message = [
            'seq' => $seq,
            'type' => LiveStreamMessage::TYPE_SYSTEM,
            'body' => $body,
            'created_at' => now()->toISOString(),
            'profile' => null,
        ];

        $this->pushToBuffer($stream, $message);

        broadcast(new ChatMessageSent($stream->channel->public_id, $message));

        return $message;
    }

    public function buffer(LiveStream $stream): array
    {
        $raw = Redis::lrange(self::PREFIX.$stream->id.':chat', 0, -1);

        return collect($raw)
            ->map(fn ($item) => json_decode($item, true))
            ->filter()
            ->values()
            ->all();
    }

    public function delete(LiveStream $stream, int $seq): void
    {
        $key = self::PREFIX.$stream->id.':chat';

        foreach (Redis::lrange($key, 0, -1) as $raw) {
            $decoded = json_decode($raw, true);

            if (($decoded['seq'] ?? null) === $seq) {
                Redis::lrem($key, 1, $raw);
                break;
            }
        }

        LiveStreamMessage::where('live_stream_id', $stream->id)
            ->where('seq', $seq)
            ->delete();

        broadcast(new ChatMessageDeleted($stream->channel->public_id, $seq));
    }

    public function isModerator(LiveStream $stream, Profile $profile): bool
    {
        if ((int) $stream->profile_id === (int) $profile->id) {
            return true;
        }

        return LiveChannelModerator::where('live_channel_id', $stream->live_channel_id)
            ->where('profile_id', $profile->id)
            ->exists();
    }

    protected function assertCanSend(LiveStream $stream, Profile $profile): void
    {
        $channel = $stream->channel;

        if (! $stream->isLive() || ! $channel || ! $channel->chat_enabled) {
            throw ValidationException::withMessages(['body' => 'Chat is closed for this stream.']);
        }

        if ($this->streams->isBanned($channel, $profile->id)) {
            throw ValidationException::withMessages(['body' => 'You cannot chat in this stream.']);
        }

        if (! $this->passesChatMode($stream, $profile)) {
            throw ValidationException::withMessages(['body' => 'Only certain viewers can chat right now.']);
        }

        if (! $this->withinRateLimit($stream, $profile)) {
            throw ValidationException::withMessages(['body' => 'You are sending messages too quickly.']);
        }
    }

    protected function passesChatMode(LiveStream $stream, Profile $profile): bool
    {
        $mode = $stream->channel->chat_mode;
        $hostId = (int) $stream->profile_id;
        $viewerId = (int) $profile->id;

        if ($mode === 'everyone' || $viewerId === $hostId) {
            return true;
        }

        if ($mode === 'followers') {
            return Follower::where('profile_id', $viewerId)
                ->where('following_id', $hostId)
                ->exists();
        }

        if ($mode === 'mutuals') {
            return Follower::where('profile_id', $viewerId)
                ->where('following_id', $hostId)
                ->exists()
                && Follower::where('profile_id', $hostId)
                    ->where('following_id', $viewerId)
                    ->exists();
        }

        return false;
    }

    protected function withinRateLimit(LiveStream $stream, Profile $profile): bool
    {
        $limit = (int) config('live.chat.rate_per_minute');

        if ($limit <= 0) {
            return true;
        }

        $key = self::PREFIX.$stream->id.':rl:'.$profile->id.':'.now()->format('YmdHi');
        $count = (int) Redis::incr($key);

        if ($count === 1) {
            Redis::expire($key, 120);
        }

        return $count <= $limit;
    }

    protected function pushToBuffer(LiveStream $stream, array $message): void
    {
        $key = self::PREFIX.$stream->id.':chat';
        $size = (int) config('live.chat.buffer_size');

        Redis::rpush($key, json_encode($message));
        Redis::ltrim($key, -$size, -1);
        Redis::expire($key, 21600);
    }

    protected function profilePayload(Profile $profile): array
    {
        return [
            'id' => (string) $profile->id,
            'username' => $profile->username,
            'name' => $profile->name,
            'avatar' => $profile->avatar ? $profile->avatar : url('/storage/avatars/default.jpg'),
        ];
    }
}
