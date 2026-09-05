<?php

namespace App\Events\Live;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class StreamStateChanged implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $queue = 'livechat';

    public function __construct(
        public string $publicId,
        public int $streamId,
        public string $status
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PresenceChannel('live.'.$this->publicId),
            new Channel('live.'.$this->publicId.'.guest'),
            new Channel('live.'.$this->publicId.'.host'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'stream.state';
    }

    public function broadcastWith(): array
    {
        return [
            'stream_id' => $this->streamId,
            'status' => $this->status,
        ];
    }
}
