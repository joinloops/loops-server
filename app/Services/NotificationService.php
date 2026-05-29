<?php

namespace App\Services;

use App\Jobs\PushNotifications\SendPushNotificationJob;
use App\Models\Notification;
use App\Models\StarterKit;
use App\Services\ConfigService;
use Illuminate\Support\Facades\Cache;

class NotificationService
{
    const NOTIFY_UNREAD_KEY = 'api:s:notify:unread:';

    const NOTIFY_TOTAL_UNREAD_KEY = 'api:s:notify:unread:total_counts:';

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

    public static function getTotalUnreadCount($profileId)
    {
        if (! $profileId) {
            return 0;
        }

        return Cache::remember(
            self::NOTIFY_TOTAL_UNREAD_KEY.$profileId,
            now()->addHours(24),
            function () use ($profileId) {
                return [
                    'activity' => Notification::whereUserId($profileId)->whereIn('type', Notification::activityTypes())->whereNull('read_at')->count(),
                    'followers' => Notification::whereUserId($profileId)->whereIn('type', Notification::followerTypes())->whereNull('read_at')->count(),
                    'system' => Notification::whereUserId($profileId)->whereIn('type', Notification::systemTypes())->whereNull('read_at')->count(),
                    'starterKits' => Notification::whereUserId($profileId)->whereIn('type', Notification::starterKitTypes())->whereNull('read_at')->count(),
                ];
            }
        );
    }

    public static function clearUnreadCount($profileId)
    {
        if (! $profileId) {
            return 0;
        }

        Cache::forget(self::NOTIFY_TOTAL_UNREAD_KEY.$profileId);

        return Cache::forget(self::NOTIFY_UNREAD_KEY.$profileId);
    }

    public static function newVideoLike($uid, $vid, $pid)
    {
        $res = Notification::updateOrCreate([
            'type' => Notification::VIDEO_LIKE,
            'user_id' => $uid,
            'video_id' => $vid,
            'profile_id' => $pid,
        ]);
        self::clearUnreadCount($uid);

        $video = Video::with('profile_id')->find($vid);
        if (app(ConfigService::class)->pushNotifications() && $uid != $video->profile_id && ! $video->uri) {
            SendPushNotificationJob::dispatch_newVideoLike(
                profileId: $uid,
                videoId: $vid,
                actorId: $pid,
                );
        }
        
        return $res;
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

    public static function deleteCommentReplyLike($uid, $pid, $crid, $vid)
    {
        $res = Notification::where([
            'type' => Notification::VIDEO_COMMENT_REPLY_LIKE,
            'user_id' => $uid,
            'profile_id' => $pid,
            'comment_reply_id' => $crid,
            'video_id' => $vid,
        ])->first();

        if ($res) {
            $res->delete();
            self::clearUnreadCount($uid);
        }
    }

    public static function newFollower($uid, $pid)
    {
        $res = Notification::updateOrCreate([
            'type' => Notification::NEW_FOLLOWER,
            'user_id' => $uid,
            'profile_id' => $pid,
        ]);

        self::clearUnreadCount($uid);

        if (app(ConfigService::class)->pushNotifications()) {
                    SendPushNotificationJob::dispatch_newFollow(
                        profileId: $uid,
                        actorId: $pid,
                    );
        }
        return $res;
    }

    public static function newFollowerFromStarterKit($uid, $pid, StarterKit $starterKit)
    {
        $res = Notification::updateOrCreate([
            'type' => Notification::NEW_FOLLOWER,
            'user_id' => $uid,
            'profile_id' => $pid,
        ], [
            'meta' => [
                'starter_kit_id' => $starterKit->id,
            ],
        ]);

        self::clearUnreadCount($uid);

        return $res;
    }

    public static function starterKitNewMember(
        int $uid,
        int $pid,
        int $starterKitId,
        int $newMemberProfileId,
    ) {
        $res = Notification::create([
            'type' => Notification::KIT_YOU_IN_NEW_MEMBERS,
            'user_id' => $uid,
            'profile_id' => $pid,
            'meta' => [
                'starter_kit_id' => $starterKitId,
                'new_profile_id' => $newMemberProfileId,
            ],
        ]);

        self::clearUnreadCount($uid);

        return $res;
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
        $res = Notification::updateOrCreate([
            'type' => Notification::NEW_VIDCOMMENT,
            'user_id' => $uid,
            'profile_id' => $pid,
            'video_id' => $vid,
            'comment_id' => $cid,
        ]);
        self::clearUnreadCount($uid);

        if (app(ConfigService::class)->pushNotifications()) {
            SendPushNotificationJob::dispatch_newVideoComment(
                profileId: $uid,
                videoId: $vid,
                actorId: $pid,
                commentId: $cid,
            );
        }
        
        return $res;
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

        if (app(ConfigService::class)->pushNotifications()) {
                    SendPushNotificationJob::dispatch_newVideoCommentReply(
                        profileId: $uid,
                        videoId: $vid,
                        actorId: $pid,
                        commentId: $cid,
                    );
        }
        
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

    public static function newVideoShare($uid, $vid, $pid)
    {
        $res = Notification::updateOrCreate([
            'type' => Notification::VIDEO_SHARE,
            'user_id' => $uid,
            'video_id' => $vid,
            'profile_id' => $pid,
        ]);
        self::clearUnreadCount($uid);

        if (app(ConfigService::class)->pushNotifications()) {
                        SendPushNotificationJob::dispatch_newRepost(
                            profileId: $uid,
                            videoId: $vid,
                            actorId: $pid,
                        );
        }
        return $res;
    }

    public static function deleteVideoShare($uid, $vid, $pid)
    {
        $res = Notification::where([
            'type' => Notification::VIDEO_SHARE,
            'user_id' => $uid,
            'video_id' => $vid,
            'profile_id' => $pid,
        ])->first();

        if ($res) {
            $res->delete();
            self::clearUnreadCount($uid);
        }
    }

    public static function newVideoCommentShare($uid, $cid, $vid, $pid)
    {
        $res = Notification::updateOrCreate([
            'type' => Notification::VIDEO_COMMENT_SHARE,
            'user_id' => $uid,
            'comment_id' => $cid,
            'video_id' => $vid,
            'profile_id' => $pid,
        ]);
        self::clearUnreadCount($uid);

        return $res;
    }

    public static function deleteVideoCommentShare($uid, $cid, $vid, $pid)
    {
        $res = Notification::where([
            'type' => Notification::VIDEO_COMMENT_SHARE,
            'user_id' => $uid,
            'comment_id' => $cid,
            'video_id' => $vid,
            'profile_id' => $pid,
        ])->first();

        if ($res) {
            $res->delete();
            self::clearUnreadCount($uid);
        }
    }

    public static function newVideoReplyShare($uid, $cid, $vid, $pid)
    {
        $res = Notification::updateOrCreate([
            'type' => Notification::VIDEO_REPLY_SHARE,
            'user_id' => $uid,
            'comment_reply_id' => $cid,
            'video_id' => $vid,
            'profile_id' => $pid,
        ]);
        self::clearUnreadCount($uid);

        return $res;
    }

    public static function deleteVideoReplyShare($uid, $cid, $vid, $pid)
    {
        $res = Notification::where([
            'type' => Notification::VIDEO_REPLY_SHARE,
            'user_id' => $uid,
            'comment_reply_id' => $cid,
            'video_id' => $vid,
            'profile_id' => $pid,
        ])->first();

        if ($res) {
            $res->delete();
            self::clearUnreadCount($uid);
        }
    }

    public static function starterKitAddAccount($uid, $pid, $kitId)
    {
        if ($uid == $pid) {
            return;
        }

        $res = Notification::updateOrCreate([
            'type' => Notification::KIT_AWAIT_APPROVAL,
            'user_id' => $uid,
            'profile_id' => $pid,
            'meta' => [
                'starter_kit_id' => $kitId,
            ],
        ]);
        self::clearUnreadCount($uid);

        return $res;
    }

    public static function starterKitAutoAdd($uid, $pid, $kitId)
    {
        if ($uid == $pid) {
            return;
        }

        $res = Notification::updateOrCreate([
            'type' => Notification::KIT_AUTO_APPROVE_ADDED_YOU,
            'user_id' => $uid,
            'profile_id' => $pid,
            'meta' => [
                'starter_kit_id' => $kitId,
            ],
        ]);
        self::clearUnreadCount($uid);

        return $res;
    }

    public static function starterKitAutoAddYouFollow($uid, $pid, $kitId)
    {
        if ($uid == $pid) {
            return;
        }

        $res = Notification::updateOrCreate([
            'type' => Notification::KIT_ONLY_YOU_FOLLOW_ADDED_YOU,
            'user_id' => $uid,
            'profile_id' => $pid,
            'meta' => [
                'starter_kit_id' => $kitId,
            ],
        ]);
        self::clearUnreadCount($uid);

        return $res;
    }

    public static function starterKitAccountApproved($uid, $pid, $kitId)
    {
        if ($uid == $pid) {
            return;
        }

        $res = Notification::updateOrCreate([
            'type' => Notification::KIT_ACCOUNT_APPROVED,
            'user_id' => $uid,
            'profile_id' => $pid,
            'meta' => [
                'starter_kit_id' => $kitId,
            ],
        ]);
        self::clearUnreadCount($uid);

        return $res;
    }

    public static function starterKitAccountRejected($uid, $pid, $kitId)
    {
        if ($uid == $pid) {
            return;
        }

        $res = Notification::updateOrCreate([
            'type' => Notification::KIT_ACCOUNT_REJECTED,
            'user_id' => $uid,
            'profile_id' => $pid,
            'meta' => [
                'starter_kit_id' => $kitId,
            ],
        ]);
        self::clearUnreadCount($uid);

        return $res;
    }

    public static function starterKitAddAccountDelete($uid, $pid, $kitId)
    {
        $res = Notification::whereIn('type', [
            Notification::KIT_AWAIT_APPROVAL,
            Notification::KIT_ONLY_YOU_FOLLOW_ADDED_YOU,
            Notification::KIT_AUTO_APPROVE_ADDED_YOU,
        ])->where([
            'user_id' => $uid,
            'profile_id' => $pid,
            'meta->starter_kit_id' => $kitId,
        ])->first();

        if ($res) {
            $res->delete();
            self::clearUnreadCount($uid);
        }
    }

    public static function starterKitYourAccountRemoved($uid, $pid, $kitId)
    {
        if ($uid == $pid) {
            return;
        }

        $res = Notification::updateOrCreate([
            'type' => Notification::KIT_YOU_IN_REMOVED,
            'user_id' => $uid,
            'profile_id' => $pid,
            'meta' => [
                'starter_kit_id' => $kitId,
            ],
        ]);
        self::clearUnreadCount($uid);

        return $res;
    }
}
