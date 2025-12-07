<?php

namespace App\Http\Resources;

use App\Models\Playlist;
use App\Services\AccountService;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Playlist
 */
class PlaylistResource extends JsonResource
{
    public function __construct(Playlist $resource)
    {
        parent::__construct($resource);
    }

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        $account = AccountService::compact($this->profile_id);

        return [
            'id' => $this->id,
            'profile_id' => $this->profile_id,
            'name' => $this->name,
            'description' => $this->description,
            'visibility' => $this->visibility,
            'cover_image' => $this->cover_image,
            'videos_count' => $this->videos_count ?? $this->videos()->count(),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'profile' => $account,
        ];
    }
}
