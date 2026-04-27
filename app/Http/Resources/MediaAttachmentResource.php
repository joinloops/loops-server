<?php

namespace App\Http\Resources;

use App\Models\MediaAttachments;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin MediaAttachments
 */
class MediaAttachmentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $url = $this->cache_url ?? $this->remote_url;

        return [
            'id' => (string) $this->id,
            'mime' => $this->mime_type ?? 'unknown',
            'url' => $url,
            'description' => $this->description,
            'width' => $this->width,
            'height' => $this->height,
            'order' => $this->order,
            'blurhash' => $this->blurhash,
            'is_sensitive' => $this->is_sensitive,
            'provider' => $this->provider,
        ];
    }
}
