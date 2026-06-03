<?php

namespace App\Http\Resources;

use App\Models\Profile;
use App\Services\AvatarService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Profile
 */
class ProfileResource extends JsonResource
{
    public function __construct(Profile $resource)
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
        $avatarUrl = $this->avatar ?? url('/storage/avatars/default.jpg');

        if ($this->uri || $this->domain) {
            $avatarUrl = AvatarService::remote($this->id);
        }

        $remoteUrl = null;

        if (! $this->local) {
            $remoteUrl = $this->remote_url ?? $this->uri;
        }

        return [
            'id' => (string) $this->id,
            'name' => $this->name ?? 'user'.$this->id,
            'avatar' => $avatarUrl,
            'username' => $this->username,
            'is_owner' => (string) $request->user()?->profile_id === (string) $this->id,
            'local' => (bool) $this->local,
            'bio' => $this->bio ?? '',
            'post_count' => $this->video_count,
            'follower_count' => $this->followers,
            'following_count' => $this->following,
            'url' => url('/@'.$this->username),
            'remote_url' => $remoteUrl,
            'is_blocking' => null,
            'manually_approves_followers' => $this->manuallyApprovesFollowers,
            'links' => $this->links ?? [],
            'has_playlists' => $this->has_playlists,
            'is_admin' => $this->user_id ? $this->when(
                $this->relationLoaded('user'),
                fn () => (bool) $this->user?->is_admin
            ) : false,
            'created_at' => $this->created_at->format('c'),
        ];
    }
}
