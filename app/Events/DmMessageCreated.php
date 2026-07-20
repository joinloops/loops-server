<?php

namespace App\Events;

use App\Http\Resources\DmMessageResource;
use App\Models\Message;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DmMessageCreated implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public bool $afterCommit = true;

    public function __construct(
        public Message $message
    ) {}

    public function broadcastOn(): array
    {
        return $this->message->conversation->participants
            ->filter(fn ($p) => $p->profile && $p->profile->domain === null)
            ->map(fn ($p) => new PrivateChannel('dm.'.$p->profile_id))
            ->values()
            ->all();
    }

    public function broadcastAs(): string
    {
        return 'dm.message.created';
    }

    public function broadcastWith(): array
    {
        return [
            'conversation_id' => (string) $this->message->conversation_id,
            'message' => (new DmMessageResource($this->message))->resolve(),
        ];
    }
}
