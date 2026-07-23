<?php

namespace App\Http\Resources;

use App\Models\Conversation;
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

        $isGroup = $conversation->type === Conversation::TYPE_GROUP;
        $lastMessage = $conversation->lastMessage;

        $others = $conversation->participants
            ->where('profile_id', '!=', $participant->profile_id)
            ->values();

        $primary = $isGroup
            ? ($others->first(
                fn (ConversationParticipant $other) => $other->state === ConversationParticipant::STATE_ACTIVE
                    && $other->profile !== null
            ) ?? $others->first(
                fn (ConversationParticipant $other) => $other->profile !== null
            ))
            : $conversation->otherParticipant($participant->profile_id);

        return [
            'id' => (string) $conversation->id,
            'type' => $isGroup ? 'group' : 'dm',
            'title' => $conversation->title,
            'state' => $participant->state,
            'muted' => $participant->muted_at !== null,
            'hidden' => $participant->hidden_at !== null,
            'pending_acceptance' => ! $isGroup
                && $primary?->state === ConversationParticipant::STATE_REQUEST,
            'unread' => $lastMessage !== null
                && (int) $lastMessage->profile_id !== (int) $participant->profile_id
                && (int) $conversation->last_message_id > (int) ($participant->last_read_message_id ?? 0),
            'last_read_message_id' => $participant->last_read_message_id
                ? (string) $participant->last_read_message_id
                : null,
            'participant' => $primary ? $this->participantPayload($primary) : null,
            'participants' => $others
                ->map(fn (ConversationParticipant $other) => $this->participantPayload($other))
                ->filter()
                ->values()
                ->all(),
            'last_message' => $lastMessage ? new DmMessageResource($lastMessage) : null,
            'updated_at' => $conversation->last_message_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function participantPayload(ConversationParticipant $other): ?array
    {
        $profile = $other->profile;

        if ($profile === null) {
            return null;
        }

        return [
            'id' => (string) $profile->id,
            'username' => $profile->username,
            'name' => $profile->name ?? $profile->username,
            'avatar' => $profile->avatar ?? url('/storage/avatars/default.jpg'),
            'domain' => $profile->domain,
            'is_remote' => $profile->domain !== null,
            'state' => $other->state,
        ];
    }
}
