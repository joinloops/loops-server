<?php

namespace App\Federation\Handlers;

use App\Models\Comment;
use App\Models\CommentLike;
use App\Models\CommentReply;
use App\Models\CommentReplyLike;
use App\Models\CommentReplyRepost;
use App\Models\CommentRepost;
use App\Models\Follower;
use App\Models\Profile;
use App\Models\Video;
use App\Models\VideoLike;
use App\Models\VideoRepost;
use App\Services\SanitizeService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class UndoHandler extends BaseHandler
{
    public function handle(array $activity, Profile $actor, ?Profile $target = null)
    {
        $object = $activity['object'];

        if (! is_array($object) || ! isset($object['type'])) {
            throw new \Exception('Invalid Undo object format');
        }

        try {
            DB::beginTransaction();

            $result = match ($object['type']) {
                'Follow' => $this->handleUndoFollow($activity, $actor, $object),
                'Like' => $this->handleUndoLike($activity, $actor, $object),
                'Announce' => $this->handleUndoAnnounce($activity, $actor, $object),
                default => $this->handleGenericUndo($activity, $actor, $object)
            };

            DB::commit();

            if (config('logging.dev_log')) {
                Log::info('Successfully handled Undo activity', [
                    'actor' => $actor->username,
                    'undo_type' => $object['type'],
                    'activity_id' => $activity['id'] ?? 'unknown',
                ]);
            }

            return $result;

        } catch (\Exception $e) {
            DB::rollBack();

            if (config('logging.dev_log')) {
                Log::error('Failed to handle Undo activity', [
                    'actor' => $actor->username,
                    'undo_type' => $object['type'],
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }

            throw $e;
        }
    }

    private function handleUndoFollow(array $activity, Profile $actor, array $followObject): bool
    {
        $targetUrl = $followObject['object'];
        $targetProfile = $this->findLocalProfile($targetUrl);

        if (! $targetProfile) {
            throw new \Exception("Target profile not found for unfollow: {$targetUrl}");
        }

        $existingFollow = Follower::where('profile_id', $actor->id)
            ->where('following_id', $targetProfile->id)
            ->first();

        if (! $existingFollow) {
            if (config('logging.dev_log')) {
                Log::info('Undo Follow - relationship does not exist', [
                    'actor' => $actor->username,
                    'target' => $targetProfile->username,
                ]);
            }

            return true;
        }

        $existingFollow->delete();

        if ($targetProfile->followers > 1) {
            $targetProfile->decrement('followers');
        }
        if ($actor->following > 1) {
            $actor->decrement('following');
        }

        if (config('logging.dev_log')) {
            Log::info('Successfully processed Undo Follow', [
                'actor' => $actor->username,
                'target' => $targetProfile->username,
            ]);
        }

        return true;
    }

    private function handleUndoLike(array $activity, Profile $actor, array $likeObject): bool
    {
        $statusUrl = $likeObject['object'];
        $status = $this->findLocalStatus($statusUrl);

        if (! $status) {
            throw new \Exception("Target status not found for unlike: {$statusUrl}");
        }

        $statusClass = get_class($status);

        match ($statusClass) {
            Video::class => $this->handleVideoUndoLike($status, $actor),
            Comment::class => $this->handleCommentUndoLike($status, $actor),
            CommentReply::class => $this->handleCommentReplyUndoLike($status, $actor),
            default => null,
        };

        return true;
    }

    private function handleVideoUndoLike($status, Profile $actor)
    {
        $existingLike = VideoLike::where('profile_id', $actor->id)
            ->where('video_id', $status->id)
            ->first();

        if (! $existingLike) {
            if (config('logging.dev_log')) {
                Log::info('Undo Video Like - like does not exist', [
                    'actor' => $actor->username,
                    'video_id' => $status->id,
                ]);
            }

            return true;
        }

        $existingLike->delete();

        if ($status->likes > 0) {
            $status->decrement('likes');
        }

        if (config('logging.dev_log')) {
            Log::info('Successfully processed Undo Like', [
                'actor' => $actor->username,
                'video_id' => $status->id,
            ]);
        }

        return true;
    }

    private function handleCommentUndoLike($status, Profile $actor)
    {
        $existingLike = CommentLike::where('profile_id', $actor->id)
            ->where('comment_id', $status->id)
            ->first();

        if (! $existingLike) {
            if (config('logging.dev_log')) {
                Log::info('Undo Comment Like - like does not exist', [
                    'actor' => $actor->username,
                    'comment_id' => $status->id,
                ]);
            }

            return true;
        }

        $existingLike->delete();

        if ($status->likes > 0) {
            $status->decrement('likes');
        }

        if (config('logging.dev_log')) {
            Log::info('Successfully processed Undo Like', [
                'actor' => $actor->username,
                'comment_id' => $status->id,
            ]);
        }

        return true;
    }

    private function handleCommentReplyUndoLike($status, Profile $actor)
    {
        $existingLike = CommentReplyLike::where('profile_id', $actor->id)
            ->where('comment_id', $status->id)
            ->first();

        if (! $existingLike) {
            if (config('logging.dev_log')) {
                Log::info('Undo Comment Reply Like - like does not exist', [
                    'actor' => $actor->username,
                    'comment_id' => $status->id,
                ]);
            }

            return true;
        }

        $existingLike->delete();

        if ($status->likes > 0) {
            $status->decrement('likes');
        }

        if (config('logging.dev_log')) {
            Log::info('Successfully processed Undo Like', [
                'actor' => $actor->username,
                'comment_id' => $status->id,
            ]);
        }

        return true;
    }

    private function handleVideoUndoShare($status, Profile $actor)
    {
        $existingShare = VideoRepost::where('profile_id', $actor->id)
            ->where('video_id', $status->id)
            ->first();

        if (! $existingShare) {
            if (config('logging.dev_log')) {
                Log::info('Undo Video Repost - repost does not exist', [
                    'actor' => $actor->username,
                    'video_id' => $status->id,
                ]);
            }

            return true;
        }

        $existingShare->delete();

        if ($status->shares > 0) {
            $status->decrement('shares');
        }

        if (config('logging.dev_log')) {
            Log::info('Successfully processed Undo Share', [
                'actor' => $actor->username,
                'video_id' => $status->id,
            ]);
        }

        return true;
    }

    private function handleCommentUndoShare($status, Profile $actor)
    {
        $existingShare = CommentRepost::where('profile_id', $actor->id)
            ->where('comment_id', $status->id)
            ->first();

        if (! $existingShare) {
            if (config('logging.dev_log')) {
                Log::info('Undo Comment Repost - repost does not exist', [
                    'actor' => $actor->username,
                    'comment_id' => $status->id,
                ]);
            }

            return true;
        }

        $existingShare->delete();

        if ($status->shares > 0) {
            $status->decrement('shares');
        }

        if (config('logging.dev_log')) {
            Log::info('Successfully processed Undo Comment Repost', [
                'actor' => $actor->username,
                'comment_id' => $status->id,
            ]);
        }

        return true;
    }

    private function handleCommentReplyUndoShare($status, Profile $actor)
    {
        $existingShare = CommentReplyRepost::where('profile_id', $actor->id)
            ->where('reply_id', $status->id)
            ->first();

        if (! $existingShare) {
            if (config('logging.dev_log')) {
                Log::info('Undo Comment Reply Repost - repost does not exist', [
                    'actor' => $actor->username,
                    'reply_id' => $status->id,
                ]);
            }

            return true;
        }

        $existingShare->delete();

        if ($status->shares > 0) {
            $status->decrement('shares');
        }

        if (config('logging.dev_log')) {
            Log::info('Successfully processed Undo Comment Reply Repost', [
                'actor' => $actor->username,
                'reply_id' => $status->id,
            ]);
        }

        return true;
    }

    private function handleUndoAnnounce(array $activity, Profile $actor, array $announceObject): bool
    {
        $statusUrl = $announceObject['object'];
        $status = $this->findLocalStatus($statusUrl);

        if (! $status) {
            throw new \Exception("Target status not found for un-announce: {$statusUrl}");
        }

        $statusClass = get_class($status);

        match ($statusClass) {
            Video::class => $this->handleVideoUndoShare($status, $actor),
            Comment::class => $this->handleCommentUndoShare($status, $actor),
            CommentReply::class => $this->handleCommentReplyUndoShare($status, $actor),
            default => null,
        };

        return true;
    }

    private function handleGenericUndo(array $activity, Profile $actor, array $object): bool
    {
        if (config('logging.dev_log')) {
            Log::info('Generic Undo activity - type not specifically handled', [
                'actor' => $actor->username,
                'object_type' => $object['type'],
                'object_url' => $object['object'],
            ]);
        }

        return true;
    }

    private function findLocalStatus(string $url)
    {
        $isLocal = $this->isLocalObject($url);

        if ($isLocal) {
            $statusMatch = app(SanitizeService::class)->matchUrlTemplate(
                url: $url,
                templates: [
                    '/ap/users/{userId}/video/{videoId}',
                ],
                useAppHost: true,
                constraints: ['userId' => '\d+', 'videoId' => '\d+']
            );

            if ($statusMatch && isset($statusMatch['userId'], $statusMatch['videoId'])) {
                return Video::whereProfileId($statusMatch['userId'])->whereKey($statusMatch['videoId'])->first();
            }

            $commentMatch = app(SanitizeService::class)->matchUrlTemplate(
                url: $url,
                templates: [
                    '/ap/users/{userId}/comment/{commentId}',
                ],
                useAppHost: true,
                constraints: ['userId' => '\d+', 'commentId' => '\d+']
            );

            if ($commentMatch && isset($commentMatch['userId'], $commentMatch['commentId'])) {
                return Comment::whereProfileId($commentMatch['userId'])->whereKey($commentMatch['commentId'])->first();
            }

            $commentReplyMatch = app(SanitizeService::class)->matchUrlTemplate(
                url: $url,
                templates: [
                    '/ap/users/{userId}/reply/{commentReplyId}',
                ],
                useAppHost: true,
                constraints: ['userId' => '\d+', 'commentReplyId' => '\d+']
            );

            if ($commentReplyMatch && isset($commentReplyMatch['userId'], $commentReplyMatch['commentReplyId'])) {
                return CommentReply::whereProfileId($commentReplyMatch['userId'])->whereKey($commentReplyMatch['commentReplyId'])->first();
            }

            return false;
        } else {
            $comment = Comment::where('ap_id', $url)->first();
            if ($comment) {
                return $comment;
            }

            $commentReply = CommentReply::where('ap_id', $url)->first();
            if ($commentReply) {
                return $commentReply;
            }

            $video = Video::whereUri($url)->first();
            if ($video) {
                return $video;
            }

            return false;
        }
    }

    private function findLocalProfile(string $url): Profile|false
    {
        $isLocal = $this->isLocalObject($url);

        if ($isLocal) {
            $profileMatch = app(SanitizeService::class)->matchUrlTemplate(
                url: $url,
                templates: [
                    '/ap/users/{profileId}',
                    '/@{username}',
                    '/users/{username}',
                ],
                useAppHost: true,
                constraints: ['profileId' => '\d+', 'username' => '[a-zA-Z0-9_.-]+']
            );

            if ($profileMatch) {
                if (isset($profileMatch['profileId'])) {
                    return Profile::whereLocal(true)->find($profileMatch['profileId']);
                }

                if (isset($profileMatch['username'])) {
                    return Profile::where('username', $profileMatch['username'])->whereLocal(true)->first();
                }
            }

            return false;
        }

        return Profile::where('uri', $url)->whereLocal(false)->first();
    }
}
