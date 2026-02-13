<?php

namespace App\Http\Resources;

use App\Models\VideoSound;
use App\Services\VideoService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin VideoSound
 */
class VideoSoundResource extends JsonResource
{
    public function __construct(VideoSound $resource)
    {
        parent::__construct($resource);
    }

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $originalVideo = VideoService::getMediaData($this->original_video_id);

        if (! $originalVideo) {
            return [
                'id' => (string) $this->id,
                'url' => $this->publicUrl(),
                'original_video' => false,
                'usage_count' => null,
                'allow_reuse' => null,
                'duration' => null,
            ];
        }

        return [
            'id' => (string) $this->id,
            'url' => $this->publicUrl(),
            'key' => substr($this->fingerprint_hash, 0, 32),
            'original_video' => $originalVideo,
            'usage_count' => $this->usage_count,
            'allow_reuse' => $this->allow_reuse,
            'duration' => $this->duration,
        ];
    }
}
