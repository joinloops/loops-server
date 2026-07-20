<?php

namespace App\Events;

use App\Models\Conversation;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DmMessageDeleted implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public bool $afterCommit = true;

    public function __construct(
        public Conversation $conversation,
        public string $messageId
    ) {}

    public function broadcastOn(): array
    {
        return $this->conversation->participants
            ->filter(fn ($p) => $p->profile && $p->profile->domain === null)
            ->map(fn ($p) => new PrivateChannel('dm.'.$p->profile_id))
            ->values()
            ->all();
    }

    public function broadcastAs(): string
    {
        return 'dm.message.deleted';
    }

    public function broadcastWith(): array
    {
        return [
            'conversation_id' => (string) $this->conversation->id,
            'message_id' => $this->messageId,
        ];
    }
}
