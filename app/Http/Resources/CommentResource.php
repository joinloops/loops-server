<?php

namespace App\Http\Resources;

use App\Services\AccountService;
use App\Services\LikeService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CommentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $pid = $request->user() ? $request->user()->profile_id : false;

        if ($this->deleted_at && $this->status != 'active') {
            $msg = $this->status === 'deleted_by_user' ?
                'This comment was deleted by the user' :
                'This comment was deleted by admins';

            return [
                'id' => (string) $this->id,
                'v_id' => (string) $this->video_id,
                'account' => AccountService::deletedAccount(),
                'caption' => $msg,
                'likes' => 0,
                'replies' => $this->replies ?? 0,
                'children' => [],
                'liked' => false,
                'url' => null,
                'is_owner' => false,
                'tombstone' => true,
                'created_at' => $this->created_at->format('c'),
            ];
        }

        $res = [
            'id' => (string) $this->id,
            'v_id' => (string) $this->video_id,
            'account' => AccountService::compact($this->profile_id),
            'caption' => $this->caption,
            'likes' => $this->likes ?? 0,
            'replies' => $this->replies ?? 0,
            'children' => [],
            'liked' => $pid ? LikeService::hasCommentLike($this->id, $pid) : false,
            'url' => $this->shareUrl(),
            'tombstone' => false,
            'is_owner' => $pid ? (string) $this->profile_id === (string) $pid : false,
            'created_at' => $this->created_at->format('c'),
        ];

        return $res;
    }
}
