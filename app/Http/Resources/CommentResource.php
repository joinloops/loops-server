<?php

namespace App\Http\Resources;

use App\Models\Comment;
use App\Models\Hashtag;
use App\Services\AccountService;
use App\Services\LikeService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Comment
 */
class CommentResource extends JsonResource
{
    public function __construct(Comment $resource)
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
        $pid = false;
        $user = auth('web')->user() ?? auth('api')->user();
        if ($user) {
            $pid = $user->profile_id;
        }

        /** @var string $status */
        $status = $this->status;

        if ($status != 'active') {
            $msg = match ($status) {
                'deleted_by_user' => 'This comment was deleted by the user',
                'deleted_by_admin' => 'This comment was deleted by admins',
                'account_disabled' => 'This comment was made by a disabled account',
                'account_pending_deletion' => 'This comment was deleted by the user',
                default => 'This comment is not available',
            };

            return [
                'id' => (string) $this->id,
                'v_id' => (string) $this->video_id,
                'account' => AccountService::deletedAccount(),
                'caption' => $msg,
                'likes' => 0,
                'replies' => $this->replies ?? 0,
                'children' => [],
                'tags' => [],
                'mentions' => [],
                'is_edited' => false,
                'liked' => false,
                'url' => null,
                'is_owner' => false,
                'is_hidden' => false,
                'tombstone' => true,
                'created_at' => $this->created_at->format('c'),
            ];
        }

        $res = [
            'id' => (string) $this->id,
            'v_id' => (string) $this->video_id,
            'account' => AccountService::compact($this->profile_id),
            'caption' => $this->caption,
            'replies' => $this->replies ?? 0,
            'children' => [],
            'tags' => $this->hashtags->map(fn (Hashtag $tag) => $tag->name),
            'mentions' => $this->mentions,
            'likes' => $this->likes ?? 0,
            'liked' => $pid ? app(LikeService::class)->hasLikedComment((string) $this->id, (string) $pid) : false,
            'url' => $this->shareUrl(),
            'tombstone' => false,
            'is_edited' => $this->is_edited,
            'is_hidden' => $this->is_hidden,
            'is_owner' => $pid ? (string) $this->profile_id === (string) $pid : false,
            'created_at' => $this->created_at->format('c'),
        ];

        return $res;
    }
}
