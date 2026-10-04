<?php

namespace App\Http\Resources;

use App\Models\Message;
use Illuminate\Http\Request;

/**
 * @mixin Message
 */
class AdminConversationMessageResource extends DmMessageResource
{
    public function toArray(Request $request): array
    {
        return array_merge(parent::toArray($request), [
            'sender' => AdminConversationResource::profilePayload(
                $this->relationLoaded('sender') ? $this->sender : null,
                $this->profile_id
            ),
            'deleted_at' => $this->deleted_at?->toIso8601String(),
        ]);
    }
}
