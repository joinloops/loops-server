<?php

namespace App\Http\Resources;

use App\Models\Notification;
use App\Services\AccountService;
use App\Services\HashidService;
use App\Services\VideoService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Notification
 */
class NotificationResource extends JsonResource
{
    public function __construct(Notification $resource)
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
        return match ($this->type) {
            Notification::VIDEO_LIKE => $this->newVideoLike(),
            Notification::NEW_FOLLOWER => $this->newFollower(),
            Notification::NEW_VIDCOMMENT => $this->newVideoComment(),
            Notification::NEW_VIDCOMMENTREPLY => $this->newVideoCommentReply(),
            Notification::VIDEO_COMMENT_LIKE => $this->newVideoCommentLike(),
            Notification::VIDEO_COMMENT_REPLY_LIKE => $this->newVideoCommentReplyLike(),
            Notification::DUET_YOUR_VIDEO => $this->newVideoDuet(),
            default => [
                'id' => (string) $this->id,
                'type' => 'internal',
                'read_at' => $this->read_at,
                'created_at' => $this->created_at,
            ],
        };
    }

    protected function newVideoDuet()
    {
        $video = VideoService::getMediaData($this->video_id);
        $thumb = data_get($video, 'media.thumbnail', null);
        $videoPid = data_get($video, 'account.id', null);
        $vhid = HashidService::encode($this->video_id);
        $link = '/v/'.$vhid;

        return [
            'id' => (string) $this->id,
            'type' => 'video.duet',
            'actor' => AccountService::compact($this->profile_id),
            'video_pid' => $videoPid,
            'video_id' => (string) $this->video_id,
            'video_thumbnail' => $thumb,
            'url' => $link,
            'read_at' => $this->read_at,
            'created_at' => $this->created_at,
        ];
    }

    protected function newVideoCommentReply()
    {
        $video = VideoService::getMediaData($this->video_id);
        $thumb = data_get($video, 'media.thumbnail', null);
        $videoPid = data_get($video, 'account.id', null);
        $vhid = HashidService::encode($this->video_id);
        $hid = HashidService::encode($this->comment_reply_id);
        $link = '/v/'.$vhid.'?rid='.$hid;

        return [
            'id' => (string) $this->id,
            'type' => 'video.commentReply',
            'actor' => AccountService::compact($this->profile_id),
            'video_pid' => $videoPid,
            'video_id' => (string) $this->video_id,
            'video_thumbnail' => $thumb,
            'url' => $link,
            'read_at' => $this->read_at,
            'created_at' => $this->created_at,
        ];
    }

    protected function newVideoCommentReplyLike()
    {
        $video = VideoService::getMediaData($this->video_id);
        $thumb = data_get($video, 'media.thumbnail', null);
        $videoPid = data_get($video, 'account.id', null);
        $vhid = HashidService::encode($this->video_id);
        $hid = HashidService::encode($this->comment_reply_id);
        $link = '/v/'.$vhid.'?rid='.$hid;

        return [
            'id' => (string) $this->id,
            'type' => 'commentReply.like',
            'video_pid' => $videoPid,
            'video_id' => (string) $this->video_id,
            'video_thumbnail' => $thumb,
            'actor' => AccountService::compact($this->profile_id),
            'url' => $link,
            'read_at' => $this->read_at,
            'created_at' => $this->created_at,
        ];
    }

    protected function newVideoCommentLike()
    {
        $video = VideoService::getMediaData($this->video_id);
        $videoPid = data_get($video, 'account.id', null);
        $thumb = data_get($video, 'media.thumbnail', null);
        $vhid = HashidService::encode($this->video_id);
        $hid = HashidService::encode($this->comment_id);
        $link = '/v/'.$vhid.'?cid='.$hid;

        return [
            'id' => (string) $this->id,
            'type' => 'comment.like',
            'video_pid' => $videoPid,
            'video_id' => (string) $this->video_id,
            'video_thumbnail' => $thumb,
            'url' => $link,
            'actor' => AccountService::compact($this->profile_id),
            'read_at' => $this->read_at,
            'created_at' => $this->created_at,
        ];
    }

    protected function newVideoLike()
    {
        $video = VideoService::getMediaData($this->video_id);
        $thumb = data_get($video, 'media.thumbnail', null);
        $videoPid = data_get($video, 'account.id', null);

        return [
            'id' => (string) $this->id,
            'type' => 'video.like',
            'video_pid' => $videoPid,
            'video_id' => (string) $this->video_id,
            'video_thumbnail' => $thumb,
            'actor' => AccountService::compact($this->profile_id),
            'read_at' => $this->read_at,
            'created_at' => $this->created_at,
        ];
    }

    protected function newFollower()
    {
        return [
            'id' => (string) $this->id,
            'type' => 'new_follower',
            'actor' => AccountService::compact($this->profile_id),
            'read_at' => $this->read_at,
            'created_at' => $this->created_at,
        ];
    }

    protected function newVideoComment()
    {
        $video = VideoService::getMediaData($this->video_id);
        $thumb = data_get($video, 'media.thumbnail', null);
        $vhid = HashidService::encode($this->video_id);
        $hid = HashidService::encode($this->comment_id);
        $videoPid = data_get($video, 'account.id', null);
        $link = '/v/'.$vhid.'?cid='.$hid;

        return [
            'id' => (string) $this->id,
            'type' => 'video.comment',
            'actor' => AccountService::compact($this->profile_id),
            'video_pid' => $videoPid,
            'video_id' => (string) $this->video_id,
            'video_thumbnail' => $thumb,
            'url' => $link,
            'read_at' => $this->read_at,
            'created_at' => $this->created_at,
        ];
    }
}
