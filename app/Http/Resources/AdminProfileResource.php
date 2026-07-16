<?php

namespace App\Http\Resources;

use App\Models\Profile;
use App\Services\AvatarService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Profile
 */
class AdminProfileResource extends JsonResource
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

        $deleteAfter = null;

        if ($this->local && $this->status === 8) {
            $deleteAfter = $this->user->delete_after;
        }

        $statusDesc = $this->getStatusDescription();

        if ($this->local && $this->status === 1 && $this->user && ! $this->user->email_verified_at) {
            $statusDesc = 'unverified';
        }

        $lastActiveAt = null;
        $canEmbed = null;

        if ($this->local && $this->user_id) {
            $lastActiveAt = $this->user->last_active_at?->format('c');
            $canEmbed = $this->user->can_embed;
        }

        return [
            'id' => (string) $this->id,
            'name' => $this->name ?? 'user'.$this->id,
            'avatar' => $avatarUrl,
            'username' => $this->username,
            'is_owner' => false,
            'local' => (bool) $this->local,
            'bio' => $this->bio,
            'post_count' => $this->video_count,
            'follower_count' => $this->followers,
            'following_count' => $this->following,
            'url' => url('/@'.$this->username),
            'is_blocking' => null,
            'links' => $this->links ?? [],
            'is_suspended' => $this->is_suspended,
            'is_hidden' => $this->is_hidden,
            'status' => $this->status,
            'status_desc' => $statusDesc,
            'can_upload' => $this->can_upload,
            'can_share' => $this->can_share,
            'can_like' => $this->can_like,
            'can_comment' => $this->can_comment,
            'can_follow' => $this->can_follow,
            'can_create_starter_kits' => $this->can_create_starter_kits,
            'can_report' => $this->can_report,
            'can_embed' => $canEmbed,
            'can_playlist' => $this->can_playlist,
            'enforce_ai_label' => $this->enforce_ai_label,
            'enforce_ad_label' => $this->enforce_ad_label,
            'enforce_nsfw_label' => $this->enforce_nsfw_label,
            'delete_after' => $deleteAfter,
            'last_active_at' => $lastActiveAt,
            'created_at' => $this->created_at->format('c'),
        ];
    }
}
