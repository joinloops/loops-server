<?php

namespace App\Http\Resources;

use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\Profile;
use App\Services\AvatarService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Conversation
 */
class AdminConversationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $participants = $this->relationLoaded('participants')
            ? $this->participants
                ->map(fn (ConversationParticipant $participant) => array_merge(
                    self::profilePayload($participant->profile, $participant->profile_id),
                    [
                        'state' => $participant->state,
                        'joined_at' => $participant->created_at?->toIso8601String(),
                    ]
                ))
                ->values()
                ->all()
            : [];

        return [
            'id' => (string) $this->id,
            'type' => $this->type === Conversation::TYPE_GROUP ? 'group' : 'dm',
            'title' => $this->title,
            'created_by_id' => (string) $this->created_by_profile_id,
            'participants' => $participants,
            'messages_count' => (int) ($this->messages_count ?? 0),
            'profile_messages_count' => (int) ($this->resource->getAttributes()['profile_messages_count'] ?? 0),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->last_message_at?->toIso8601String(),
        ];
    }

    public static function profilePayload(?Profile $profile, int|string|null $fallbackId = null): array
    {
        if ($profile === null) {
            return [
                'id' => $fallbackId !== null ? (string) $fallbackId : null,
                'username' => null,
                'name' => null,
                'avatar' => url('/storage/avatars/default.jpg'),
                'domain' => null,
                'is_remote' => false,
                'status' => 'deleted',
            ];
        }

        $avatarUrl = $profile->avatar ?? url('/storage/avatars/default.jpg');

        if ($profile->uri || $profile->domain) {
            $avatarUrl = AvatarService::remote($profile->id);
        }

        return [
            'id' => (string) $profile->id,
            'username' => $profile->username,
            'name' => $profile->name ?? $profile->username,
            'avatar' => $avatarUrl,
            'domain' => $profile->domain,
            'is_remote' => $profile->domain !== null,
            'status' => $profile->getStatusDescription(),
        ];
    }
}
