<?php

namespace App\Events\Live;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ChatMessageDeleted implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $queue = 'livechat';

    public function __construct(
        public string $publicId,
        public int $seq
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PresenceChannel('live.'.$this->publicId),
            new Channel('live.'.$this->publicId.'.guest'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'chat.deleted';
    }

    public function broadcastWith(): array
    {
        return ['seq' => $this->seq];
    }
}
