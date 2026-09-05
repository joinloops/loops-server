<?php

namespace App\Events\Live;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ChatMessageSent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $queue = 'livechat';

    public function __construct(
        public string $publicId,
        public array $message
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
        return 'chat.message';
    }

    public function broadcastWith(): array
    {
        return $this->message;
    }
}
