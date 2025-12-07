<?php

namespace App\Services;

use App\Models\Notification;
use Illuminate\Support\Facades\Cache;

class NotificationService
{
    const NOTIFY_UNREAD_KEY = 'api:s:notify:unread:';

    public static function getUnreadCount($profileId)
    {
        if (! $profileId) {
            return 0;
        }

        return Cache::remember(
            self::NOTIFY_UNREAD_KEY.$profileId,
            now()->addHours(24),
            function () use ($profileId) {
                return Notification::whereUserId($profileId)->whereNull('read_at')->count();
            }
        );
    }

    public static function clearUnreadCount($profileId)
    {
        if (! $profileId) {
            return 0;
        }

        return Cache::forget(self::NOTIFY_UNREAD_KEY.$profileId);
    }

    public static function newVideoLike($uid, $vid, $pid)
    {
        return Notification::updateOrCreate([
            'type' => Notification::VIDEO_LIKE,
            'user_id' => $uid,
            'video_id' => $vid,
            'profile_id' => $pid,
        ]);
    }

    public static function deleteVideoLike($uid, $vid, $pid)
    {
        $res = Notification::where([
            'type' => Notification::VIDEO_LIKE,
            'user_id' => $uid,
            'video_id' => $vid,
            'profile_id' => $pid,
        ])->first();

        if ($res) {
            $res->delete();
            self::clearUnreadCount($uid);
        }
    }

    public static function newCommentLike($uid, $cid, $pid, $vid)
    {
        $res = Notification::updateOrCreate([
            'type' => Notification::VIDEO_COMMENT_LIKE,
            'user_id' => $uid,
            'profile_id' => $pid,
            'comment_id' => $cid,
            'video_id' => $vid,
        ]);
        self::clearUnreadCount($uid);

        return $res;
    }

    public static function deleteCommentLike($uid, $cid, $pid)
    {
        $res = Notification::where([
            'type' => Notification::VIDEO_COMMENT_LIKE,
            'user_id' => $uid,
            'profile_id' => $pid,
            'comment_id' => $cid,
        ])->first();

        if ($res) {
            $res->delete();
            self::clearUnreadCount($uid);
        }
    }

    public static function newCommentReplyLike($uid, $pid, $crid, $vid)
    {
        $res = Notification::updateOrCreate([
            'type' => Notification::VIDEO_COMMENT_REPLY_LIKE,
            'user_id' => $uid,
            'profile_id' => $pid,
            'comment_reply_id' => $crid,
            'video_id' => $vid,
        ]);
        self::clearUnreadCount($uid);

        return $res;
    }

    public static function newFollower($uid, $pid)
    {
        return Notification::updateOrCreate([
            'type' => Notification::NEW_FOLLOWER,
            'user_id' => $uid,
            'profile_id' => $pid,
        ]);
    }

    public static function unFollow($uid, $pid)
    {
        $res = Notification::where([
            'type' => Notification::NEW_FOLLOWER,
            'user_id' => $uid,
            'profile_id' => $pid,
        ])->first();

        if ($res) {
            $res->delete();
            self::clearUnreadCount($uid);
        }
    }

    public static function newVideoComment($pid, $uid, $vid, $cid)
    {
        return Notification::updateOrCreate([
            'type' => Notification::NEW_VIDCOMMENT,
            'user_id' => $uid,
            'profile_id' => $pid,
            'video_id' => $vid,
            'comment_id' => $cid,
        ]);
    }

    public static function newVideoDuet($pid, $uid, $vid)
    {
        return Notification::updateOrCreate([
            'type' => Notification::DUET_YOUR_VIDEO,
            'user_id' => $uid,
            'profile_id' => $pid,
            'video_id' => $vid,
        ]);
    }

    public static function deleteVideoComment($pid, $uid, $vid, $cid)
    {
        $res = Notification::where([
            'type' => Notification::NEW_VIDCOMMENT,
            'user_id' => $uid,
            'profile_id' => $pid,
            'video_id' => $vid,
            'comment_id' => $cid,
        ])->first();

        if ($res) {
            $res->delete();
            self::clearUnreadCount($uid);
        }
    }

    public static function newCommentReply($pid, $uid, $vid, $cid, $crid)
    {
        $res = Notification::updateOrCreate([
            'type' => Notification::NEW_COMMENT_REPLY,
            'user_id' => $uid,
            'profile_id' => $pid,
            'video_id' => $vid,
            'comment_id' => $cid,
            'comment_reply_id' => $crid,
        ]);
        self::clearUnreadCount($uid);

        return $res;
    }

    public static function newVideoCommentReply($pid, $uid, $vid, $cid, $crid)
    {
        $res = Notification::updateOrCreate([
            'type' => Notification::NEW_VIDCOMMENTREPLY,
            'user_id' => $uid,
            'profile_id' => $pid,
            'video_id' => $vid,
            'comment_id' => $cid,
            'comment_reply_id' => $crid,
        ]);
        self::clearUnreadCount($uid);

        return $res;
    }

    public static function deleteVideoCommentReply($pid, $uid, $vid, $cid, $crid)
    {
        $res = Notification::where([
            'type' => Notification::NEW_VIDCOMMENTREPLY,
            'user_id' => $uid,
            'profile_id' => $pid,
            'video_id' => $vid,
            'comment_id' => $cid,
            'comment_reply_id' => $crid,
        ])->first();

        if ($res) {
            $res->delete();
            self::clearUnreadCount($uid);
        }
    }
}
