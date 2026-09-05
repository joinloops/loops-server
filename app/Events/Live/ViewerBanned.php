<?php

namespace App\Events\Live;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ViewerBanned implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $queue = 'livechat';

    public function __construct(
        public string $publicId,
        public int $profileId
    ) {}

    public function broadcastOn(): array
    {
        return [new PresenceChannel('live.'.$this->publicId)];
    }

    public function broadcastAs(): string
    {
        return 'viewer.banned';
    }

    public function broadcastWith(): array
    {
        return ['profile_id' => $this->profileId];
    }
}
