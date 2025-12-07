<?php

namespace App\Http\Resources;

use App\Models\PlaylistVideo;
use App\Models\Video;
use App\Services\VideoService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Video
 *
 * @property-read PlaylistVideo $pivot
 */
class PlaylistVideoResource extends JsonResource
{
    public function __construct(Video $resource)
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
        $video = VideoService::getMediaData($this->id);

        $video['pivot'] = [
            'position' => $this->pivot->position,
            'added_at' => $this->pivot->created_at,
        ];

        return $video;
    }
}
