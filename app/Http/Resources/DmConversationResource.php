<?php

namespace App\Http\Resources;

use App\Models\ConversationParticipant;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ConversationParticipant
 */
class DmConversationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var ConversationParticipant $participant */
        $participant = $this->resource;

        $conversation = $participant->conversation;

        if ($conversation === null) {
            return [];
        }

        $other = $conversation->otherParticipant($participant->profile_id);
        $profile = $other?->profile;
        $lastMessage = $conversation->lastMessage;

        return [
            'id' => (string) $conversation->id,
            'state' => $participant->state,
            'muted' => $participant->muted_at !== null,
            'hidden' => $participant->hidden_at !== null,
            'pending_acceptance' => $other?->state === ConversationParticipant::STATE_REQUEST,
            'unread' => $lastMessage !== null
                && (int) $lastMessage->profile_id !== (int) $participant->profile_id
                && (int) $conversation->last_message_id > (int) ($participant->last_read_message_id ?? 0),
            'last_read_message_id' => $participant->last_read_message_id
                ? (string) $participant->last_read_message_id
                : null,
            'participant' => $profile ? [
                'id' => (string) $profile->id,
                'username' => $profile->username,
                'name' => $profile->name ?? $profile->username,
                'avatar' => $profile->avatar ?? url('/storage/avatars/default.jpg'),
                'domain' => $profile->domain,
                'is_remote' => $profile->domain !== null,
            ] : null,
            'last_message' => $lastMessage ? new DmMessageResource($lastMessage) : null,
            'updated_at' => $conversation->last_message_at?->toIso8601String(),
        ];
    }
}
