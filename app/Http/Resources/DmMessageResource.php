<?php

namespace App\Http\Resources;

use App\Models\Message;
use App\Services\VideoService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Message
 */
class DmMessageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'conversation_id' => (string) $this->conversation_id,
            'sender_id' => (string) $this->profile_id,
            'type' => $this->type,
            'body' => $this->body,
            'video' => $this->when(
                $this->relationLoaded('video') && $this->video,
                fn () => app(VideoService::class)->getMediaData($this->video->id),
            ),
            'media' => data_get($this->entities, 'media', []),
            'created_at' => $this->created_at->toIso8601String(),
            'edited_at' => $this->edited_at?->toIso8601String(),
        ];
    }
}
