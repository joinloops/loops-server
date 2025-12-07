<?php

namespace App\Http\Resources;

use App\Models\Instance;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Instance
 */
class AdminInstanceResource extends JsonResource
{
    public function __construct(Instance $resource)
    {
        parent::__construct($resource);
    }

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request)
    {
        $status = 'active';
        if ($this->is_blocked) {
            $status = 'suspended';
        }
        if ($this->is_silenced) {
            $status = 'pending';
        }

        return [
            'id' => $this->id,
            'domain' => $this->domain,
            'description' => $this->description,
            'federation_enabled' => (bool) $this->federation_state == 5,
            'software' => $this->software,
            'version' => $this->version,
            'is_silenced' => (bool) $this->is_silenced,
            'is_blocked' => (bool) $this->is_blocked,
            'user_count' => $this->user_count ?? 0,
            'video_count' => $this->video_count ?? 0,
            'reply_count' => $this->reply_count ?? 0,
            'comment_count' => $this->comment_count ?? 0,
            'follower_count' => $this->follower_count ?? 0,
            'following_count' => $this->following_count ?? 0,
            'report_count' => $this->report_count ?? 0,
            'federation_state' => (int) $this->federation_state,
            'admin_notes' => $this->admin_notes,
            'status' => $status,
            'allow_video_posts' => (bool) $this->allow_video_posts,
            'allow_videos_in_fyf' => (bool) $this->allow_videos_in_fyf,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'version_last_checked_at' => $this->version_last_checked_at,
        ];
    }
}
