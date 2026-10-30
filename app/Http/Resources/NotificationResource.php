<?php

namespace App\Http\Resources;

use App\Models\Notification;
use App\Models\VideoCollaboration;
use App\Services\AccountService;
use App\Services\CollabService;
use App\Services\HashidService;
use App\Services\StarterKitService;
use App\Services\SystemMessageService;
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
            Notification::VIDEO_SHARE => $this->newVideoShare(),
            Notification::VIDEO_COMMENT_SHARE => $this->newVideoCommentShare(),
            Notification::VIDEO_REPLY_SHARE => $this->newVideoCommentReplyShare(),
            Notification::DUET_YOUR_VIDEO => $this->newVideoDuet(),
            Notification::NEW_COMMENT_REPLY => $this->newCommentReply(),
            Notification::SYSTEM_MESSAGE_INFO => $this->systemMessageInfo(),
            Notification::SYSTEM_MESSAGE_FEATURE => $this->systemMessageInfo(),
            Notification::SYSTEM_MESSAGE_UPDATE => $this->systemMessageInfo(),
            Notification::KIT_AWAIT_APPROVAL => $this->addedToStarterKit(),
            Notification::KIT_ACCOUNT_APPROVED => $this->starterKitAccountApproved(),
            Notification::KIT_ACCOUNT_REJECTED => $this->starterKitAccountRejected(),
            Notification::KIT_YOU_IN_REMOVED => $this->starterKitYouInRemoved(),
            Notification::KIT_YOU_IN_NEW_MEMBERS => $this->starterKitYouInNewMembers(),
            Notification::KIT_ONLY_YOU_FOLLOW_ADDED_YOU => $this->starterKitOnlyYouFollowAutoApprovedAdded(),
            Notification::KIT_AUTO_APPROVE_ADDED_YOU => $this->starterKitAutoApprovedAdded(),
            Notification::COLLAB_REQUEST => $this->collab('collab.request'),
            Notification::COLLAB_ACCEPTED => $this->collab('collab.accepted'),
            Notification::COLLAB_DECLINED => $this->collab('collab.declined'),
            Notification::COLLAB_AUTO_ADDED => $this->collab('collab.autoAdded'),
            Notification::COLLAB_REMOVED => $this->collab('collab.removed'),
            default => [
                'id' => (string) $this->id,
                'type' => 'internal',
                'read_at' => $this->read_at,
                'created_at' => $this->created_at,
            ],
        };
    }

    protected function systemMessageInfo()
    {
        $systemMessage = app(SystemMessageService::class)->get($this->system_message_id);
        $subType = match ($this->type) {
            8 => 'info',
            9 => 'feature',
            10 => 'update',
            default => 'info'
        };

        return [
            'id' => (string) $this->id,
            'type' => 'system.message',
            'systemType' => $subType,
            'systemMessage' => $systemMessage,
            'read_at' => $this->read_at,
            'created_at' => $this->created_at,
        ];
    }

    protected function starterKitAutoApprovedAdded()
    {
        return [
            'id' => (string) $this->id,
            'type' => 'starterKit.autoAdded',
            'actor' => AccountService::get($this->profile_id, false) ?: $this->unavailableAccount(),
            'kit' => app(StarterKitService::class)->getCompact($this->meta['starter_kit_id']),
            'read_at' => $this->read_at,
            'created_at' => $this->created_at,
        ];
    }

    protected function starterKitOnlyYouFollowAutoApprovedAdded()
    {
        return [
            'id' => (string) $this->id,
            'type' => 'starterKit.autoAddedYouFollow',
            'actor' => AccountService::get($this->profile_id, false) ?: $this->unavailableAccount(),
            'kit' => app(StarterKitService::class)->getCompact($this->meta['starter_kit_id']),
            'read_at' => $this->read_at,
            'created_at' => $this->created_at,
        ];
    }

    protected function starterKitYouInNewMembers()
    {
        return [
            'id' => (string) $this->id,
            'type' => 'starterKit.newMember',
            'actor' => AccountService::get($this->profile_id, false) ?: $this->unavailableAccount(),
            'kit' => app(StarterKitService::class)->getCompact($this->meta['starter_kit_id']),
            'new_member' => app(AccountService::class)->compact($this->meta['new_profile_id']),
            'read_at' => $this->read_at,
            'created_at' => $this->created_at,
        ];
    }

    protected function starterKitYouInRemoved()
    {
        return [
            'id' => (string) $this->id,
            'type' => 'starterKit.removedFromKit',
            'actor' => AccountService::get($this->profile_id, false) ?: $this->unavailableAccount(),
            'kit' => app(StarterKitService::class)->getCompact($this->meta['starter_kit_id']),
            'read_at' => $this->read_at,
            'created_at' => $this->created_at,
        ];
    }

    protected function addedToStarterKit()
    {
        return [
            'id' => (string) $this->id,
            'type' => 'starterKit.awaitingApproval',
            'actor' => AccountService::get($this->profile_id, false) ?: $this->unavailableAccount(),
            'kit' => app(StarterKitService::class)->getCompact($this->meta['starter_kit_id']),
            'read_at' => $this->read_at,
            'created_at' => $this->created_at,
        ];
    }

    protected function starterKitAccountApproved()
    {
        return [
            'id' => (string) $this->id,
            'type' => 'starterKit.accountApproved',
            'actor' => AccountService::get($this->profile_id, false) ?: $this->unavailableAccount(),
            'kit' => app(StarterKitService::class)->getCompact($this->meta['starter_kit_id']),
            'read_at' => $this->read_at,
            'created_at' => $this->created_at,
        ];
    }

    protected function starterKitAccountRejected()
    {
        return [
            'id' => (string) $this->id,
            'type' => 'starterKit.accountRejected',
            'actor' => AccountService::get($this->profile_id, false) ?: $this->unavailableAccount(),
            'kit' => app(StarterKitService::class)->getCompact($this->meta['starter_kit_id']),
            'read_at' => $this->read_at,
            'created_at' => $this->created_at,
        ];
    }

    protected function newCommentReply()
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
            'actor' => AccountService::get($this->profile_id, false) ?: $this->unavailableAccount(),
            'video_pid' => $videoPid,
            'video_id' => (string) $this->video_id,
            'comment_reply_id' => (string) $this->comment_reply_id,
            'video_thumbnail' => $thumb,
            'url' => $link,
            'read_at' => $this->read_at,
            'created_at' => $this->created_at,
        ];
    }

    protected function newVideoCommentReplyShare()
    {
        $video = VideoService::getMediaData($this->video_id);
        $thumb = data_get($video, 'media.thumbnail', null);
        $videoPid = data_get($video, 'account.id', null);
        $vhid = HashidService::encode($this->video_id);
        $hid = HashidService::encode($this->comment_reply_id);
        $link = '/v/'.$vhid.'?rid='.$hid;

        return [
            'id' => (string) $this->id,
            'type' => 'commentReply.share',
            'video_pid' => $videoPid,
            'video_id' => (string) $this->video_id,
            'comment_reply_id' => (string) $this->comment_reply_id,
            'video_thumbnail' => $thumb,
            'actor' => AccountService::get($this->profile_id, false) ?: $this->unavailableAccount(),
            'url' => $link,
            'read_at' => $this->read_at,
            'created_at' => $this->created_at,
        ];
    }

    protected function newVideoCommentShare()
    {
        $video = VideoService::getMediaData($this->video_id);
        $videoPid = data_get($video, 'account.id', null);
        $thumb = data_get($video, 'media.thumbnail', null);
        $vhid = HashidService::encode($this->video_id);
        $hid = HashidService::encode($this->comment_id);
        $link = '/v/'.$vhid.'?cid='.$hid;

        return [
            'id' => (string) $this->id,
            'type' => 'comment.share',
            'video_pid' => $videoPid,
            'video_id' => (string) $this->video_id,
            'comment_id' => (string) $this->comment_id,
            'video_thumbnail' => $thumb,
            'url' => $link,
            'actor' => AccountService::get($this->profile_id, false) ?: $this->unavailableAccount(),
            'read_at' => $this->read_at,
            'created_at' => $this->created_at,
        ];
    }

    protected function newVideoShare()
    {
        $video = VideoService::getMediaData($this->video_id);
        $thumb = data_get($video, 'media.thumbnail', null);
        $videoPid = data_get($video, 'account.id', null);

        return [
            'id' => (string) $this->id,
            'type' => 'video.share',
            'video_pid' => $videoPid,
            'video_id' => (string) $this->video_id,
            'video_thumbnail' => $thumb,
            'actor' => AccountService::get($this->profile_id, false) ?: $this->unavailableAccount(),
            'read_at' => $this->read_at,
            'created_at' => $this->created_at,
        ];
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
            'actor' => AccountService::get($this->profile_id, false) ?: $this->unavailableAccount(),
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
            'actor' => AccountService::get($this->profile_id, false) ?: $this->unavailableAccount(),
            'video_pid' => $videoPid,
            'video_id' => (string) $this->video_id,
            'comment_reply_id' => (string) $this->comment_reply_id,
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
            'comment_reply_id' => (string) $this->comment_reply_id,
            'video_thumbnail' => $thumb,
            'actor' => AccountService::get($this->profile_id, false) ?: $this->unavailableAccount(),
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
            'comment_id' => (string) $this->comment_id,
            'video_thumbnail' => $thumb,
            'url' => $link,
            'actor' => AccountService::get($this->profile_id, false) ?: $this->unavailableAccount(),
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
            'actor' => AccountService::get($this->profile_id, false) ?: $this->unavailableAccount(),
            'read_at' => $this->read_at,
            'created_at' => $this->created_at,
        ];
    }

    protected function newFollower()
    {
        $hasMeta = $this->meta && isset($this->meta['starter_kit_id']);

        $res = [
            'id' => (string) $this->id,
            'type' => 'new_follower',
            'actor' => AccountService::get($this->profile_id, false) ?: $this->unavailableAccount(),
            'read_at' => $this->read_at,
            'created_at' => $this->created_at,
        ];

        if ($hasMeta) {
            $res['kit'] = app(StarterKitService::class)->getCompact($this->meta['starter_kit_id']);
        }

        return $res;
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
            'actor' => AccountService::get($this->profile_id, false) ?: $this->unavailableAccount(),
            'video_pid' => $videoPid,
            'video_id' => (string) $this->video_id,
            'video_thumbnail' => $thumb,
            'comment_id' => (string) $this->comment_id,
            'url' => $link,
            'read_at' => $this->read_at,
            'created_at' => $this->created_at,
        ];
    }

    protected function collab(string $type)
    {
        $collabId = data_get($this->meta, 'collab_id');
        $collab = $collabId ? VideoCollaboration::with('video')->find($collabId) : null;
        $video = $collab?->video;
        $preview = $video ? app(CollabService::class)->videoPreview($video) : null;

        return [
            'id' => (string) $this->id,
            'type' => $type,
            'actor' => AccountService::get($this->profile_id, false) ?: $this->unavailableAccount(),
            'collab_id' => $collabId ? (string) $collabId : null,
            'collab_state' => $collab?->stateName(),
            'reason' => data_get($this->meta, 'reason'),
            'by' => data_get($this->meta, 'by'),
            'video_id' => $this->video_id ? (string) $this->video_id : null,
            'video_thumbnail' => $preview['thumbnail'] ?? null,
            'url' => $video && $video->isLive() ? '/v/'.HashidService::encode($video->id) : null,
            'read_at' => $this->read_at,
            'created_at' => $this->created_at,
        ];
    }

    protected function unavailableAccount()
    {
        return [
            'id' => 0,
            'name' => 'User',
            'username' => 'user',
            'avatar' => '/storage/avatars/default.jpg',
        ];
    }
}
