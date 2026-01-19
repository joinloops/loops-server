<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RelaySubscription extends Model
{
    protected $fillable = [
        'name',
        'relay_url',
        'relay_actor_url',
        'inbox_url',
        'shared_inbox_url',
        'status',
        'send_public_posts',
        'receive_content',
        'last_delivery_at',
        'last_received_at',
        'total_sent',
        'total_received',
        'relay_info',
    ];

    protected $casts = [
        'send_public_posts' => 'boolean',
        'receive_content' => 'boolean',
        'last_delivery_at' => 'datetime',
        'last_received_at' => 'datetime',
        'total_sent' => 'integer',
        'total_received' => 'integer',
        'relay_info' => 'array',
    ];

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function canSend(): bool
    {
        return $this->isActive() && $this->send_public_posts;
    }

    public function canReceive(): bool
    {
        return $this->isActive() && $this->receive_content;
    }

    public function getInbox(): ?string
    {
        return $this->shared_inbox_url ?? $this->inbox_url;
    }

    public function incrementSent(): void
    {
        $this->increment('total_sent');
        $this->update(['last_delivery_at' => now()]);
    }

    public function incrementReceived(): void
    {
        $this->increment('total_received');
        $this->update(['last_received_at' => now()]);
    }

    public function markAsActive(): void
    {
        $this->update(['status' => 'active']);
    }

    public function markAsRejected(): void
    {
        $this->update(['status' => 'rejected']);
    }

    public function disable(): void
    {
        $this->update(['status' => 'disabled']);
    }

    public function enable(): void
    {
        $this->update(['status' => 'active']);
    }
}
