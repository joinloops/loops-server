<?php

namespace App\Services;

use App\Models\CommentLike;
use App\Models\CommentReplyLike;
use App\Models\VideoLike;
use Cache;
use Illuminate\Support\Facades\Redis;

class LikeService
{
    const VIDEO_LIKES_KEY = 'api:s:like:video:';

    const COMMENT_LIKES_KEY = 'api:s:like:comment:';

    const COMMENT_REPLY_LIKES_KEY = 'api:s:like:reply:';

    const VIDEO_LIKES_TIMESTAMP_KEY = 'api:s:like:ts:';

    const COMMENT_LIKES_TIMESTAMP_KEY = 'api:s:like:ts:';

    const COMMENT_REPLY_LIKES_TIMESTAMP_KEY = 'api:s:like:reply:ts:';

    const CACHE_TTL = 3600;

    const MAX_CACHED_LIKES = 1000;

    const RECENT_LIKES_WINDOW = 86400;

    /**
     * Check if a profile has liked a video
     */
    public static function hasVideo(string $videoId, string $profileId)
    {
        $cacheKey = self::VIDEO_LIKES_KEY.$videoId;

        if (Redis::sismember($cacheKey, $profileId) == 1) {
            return true;
        }

        $timestampKey = self::VIDEO_LIKES_TIMESTAMP_KEY.$videoId;
        $cacheTimestamp = Redis::get($timestampKey);

        if ($cacheTimestamp && (time() - $cacheTimestamp) < self::RECENT_LIKES_WINDOW) {
            return false;
        }

        return VideoLike::where('video_id', $videoId)
            ->where('profile_id', $profileId)
            ->exists();
    }

    /**
     * Add a video like to cache
     */
    public static function addVideo(string $videoId, string $profileId)
    {
        $cacheKey = self::VIDEO_LIKES_KEY.$videoId;
        $timestampKey = self::VIDEO_LIKES_TIMESTAMP_KEY.$videoId;

        if (Redis::sismember($cacheKey, $profileId)) {
            return false;
        }

        Redis::sadd($cacheKey, $profileId);
        Redis::expire($cacheKey, self::CACHE_TTL);

        Redis::set($timestampKey, time(), ['EX' => self::CACHE_TTL]);

        return true;
    }

    /**
     * Remove a video like from cache
     */
    public static function remVideo(string $videoId, string $profileId)
    {
        $cacheKey = self::VIDEO_LIKES_KEY.$videoId;

        return Redis::srem($cacheKey, $profileId);
    }

    /**
     * Get video likes from cache
     */
    public static function getVideoCount(string $videoId)
    {
        $cacheKey = self::VIDEO_LIKES_KEY.$videoId;

        return Redis::scard($cacheKey);
    }

    /**
     * Get video likes from cache
     */
    public static function getVideo(string $videoId)
    {
        $cacheKey = self::VIDEO_LIKES_KEY.$videoId;

        return Redis::smembers($cacheKey);
    }

    /**
     * Check if a profile has liked a comment
     */
    public static function hasCommentLike($commentId, $profileId)
    {
        $cacheKey = self::COMMENT_LIKES_KEY.$commentId;

        if (Redis::sismember($cacheKey, $profileId)) {
            return true;
        }

        $timestampKey = self::COMMENT_LIKES_TIMESTAMP_KEY.$commentId;
        $cacheTimestamp = Redis::get($timestampKey);

        if ($cacheTimestamp && (time() - $cacheTimestamp) < self::RECENT_LIKES_WINDOW) {
            return false;
        }

        return CommentLike::where('comment_id', $commentId)
            ->where('profile_id', $profileId)
            ->exists();
    }

    /**
     * Add a comment like to cache
     */
    public static function addCommentLike($commentId, $profileId)
    {
        $cacheKey = self::COMMENT_LIKES_KEY.$commentId;
        $timestampKey = self::COMMENT_LIKES_TIMESTAMP_KEY.$commentId;

        if (Redis::sismember($cacheKey, $profileId)) {
            return false;
        }

        Redis::sadd($cacheKey, $profileId);
        Redis::expire($cacheKey, self::CACHE_TTL);
        Redis::set($timestampKey, time(), ['EX' => self::CACHE_TTL]);

        return true;
    }

    /**
     * Remove a comment like from cache
     */
    public static function removeCommentLike($commentId, $profileId)
    {
        $cacheKey = self::COMMENT_LIKES_KEY.$commentId;

        return Redis::srem($cacheKey, $profileId) > 0;
    }

    /**
     * Check if a profile has liked a comment reply
     */
    public static function hasCommentReplyLike($parentCommentId, $replyId, $profileId)
    {
        $cacheKey = self::COMMENT_REPLY_LIKES_KEY.$parentCommentId.':'.$replyId;

        if (Redis::sismember($cacheKey, $profileId)) {
            return true;
        }

        $timestampKey = self::COMMENT_REPLY_LIKES_TIMESTAMP_KEY.$parentCommentId.':'.$replyId;
        $cacheTimestamp = Redis::get($timestampKey);

        if ($cacheTimestamp && (time() - $cacheTimestamp) < self::RECENT_LIKES_WINDOW) {
            return false;
        }

        return CommentReplyLike::where('comment_id', $replyId)
            ->where('profile_id', $profileId)
            ->exists();
    }

    /**
     * Add a comment reply like to cache
     */
    public static function addCommentReplyLike($parentCommentId, $replyId, $profileId)
    {
        $cacheKey = self::COMMENT_REPLY_LIKES_KEY.$parentCommentId.':'.$replyId;
        $timestampKey = self::COMMENT_REPLY_LIKES_TIMESTAMP_KEY.$parentCommentId.':'.$replyId;

        if (Redis::sismember($cacheKey, $profileId)) {
            return false;
        }

        Redis::sadd($cacheKey, $profileId);
        Redis::expire($cacheKey, self::CACHE_TTL);
        Redis::set($timestampKey, time(), ['EX' => self::CACHE_TTL]);

        return true;
    }

    /**
     * Remove a comment reply like from cache
     */
    public static function removeCommentReplyLike($parentCommentId, $replyId, $profileId)
    {
        $cacheKey = self::COMMENT_REPLY_LIKES_KEY.$parentCommentId.':'.$replyId;

        return Redis::srem($cacheKey, $profileId) > 0;
    }

    /**
     * Get comment reply likes from cache
     */
    public static function getCommentReplyLikes($parentCommentId, $replyId)
    {
        $cacheKey = self::COMMENT_REPLY_LIKES_KEY.$parentCommentId.':'.$replyId;

        return Redis::smembers($cacheKey);
    }
}
