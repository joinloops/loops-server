<?php

namespace App\Events\Live;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ViewerCountUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $queue = 'livechat';

    public function __construct(
        public string $publicId,
        public int $count
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
        return 'viewers.count';
    }

    public function broadcastWith(): array
    {
        return ['count' => $this->count];
    }
}
