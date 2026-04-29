<?php

namespace App\Federation\Handlers;

use App\Models\Comment;
use App\Models\CommentLike;
use App\Models\CommentReply;
use App\Models\CommentReplyLike;
use App\Models\Profile;
use App\Models\UserFilter;
use App\Models\Video;
use App\Models\VideoLike;
use App\Services\HashidService;
use App\Services\SanitizeService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class LikeHandler extends BaseHandler
{
    public function handle(array $activity, Profile $actor, ?Profile $target = null)
    {
        $objectUrl = $activity['object'];

        try {
            DB::beginTransaction();

            $objectModel = $this->findLocalStatus($objectUrl);

            if (! $objectModel) {
                DB::commit();

                return;
            }

            $objectClass = get_class($objectModel);
            $statusOwnerId = $objectModel->profile_id;
            $like = null;

            $ownerIsBlocking = UserFilter::whereProfileId($statusOwnerId)->whereAccountId($actor->id)->exists();

            if ($ownerIsBlocking) {
                if (config('logging.dev_log')) {
                    Log::info('Status owner is blocking actor', [
                        'actor' => $actor->username,
                        'object_id' => $objectModel->id,
                        'object_type' => $objectClass,
                    ]);
                }
                DB::commit();

                return;
            }

            if ($objectClass === Video::class) {
                $existingLike = VideoLike::where('profile_id', $actor->id)
                    ->where('video_id', $objectModel->id)
                    ->first();

                if ($existingLike) {
                    if (config('logging.dev_log')) {
                        Log::info('Like already exists', [
                            'actor' => $actor->username,
                            'object_id' => $objectModel->id,
                            'object_type' => $objectClass,
                        ]);
                    }

                    DB::commit();

                    return $existingLike;
                }
                $like = $this->createVideoLike($actor, $objectModel, $activity);

                $this->updateVideoLikeCount($objectModel);

            } elseif ($objectClass === Comment::class) {
                $existingLike = CommentLike::where('profile_id', $actor->id)
                    ->where('comment_id', $objectModel->id)
                    ->first();

                if ($existingLike) {
                    if (config('logging.dev_log')) {
                        Log::info('Like already exists', [
                            'actor' => $actor->username,
                            'object_id' => $objectModel->id,
                            'object_type' => $objectClass,
                        ]);
                    }

                    DB::commit();

                    return $existingLike;
                }
                $like = $this->createCommentLike($actor, $objectModel, $activity);

                $this->updateCommentLikeCount($objectModel);
            } elseif ($objectClass === CommentReply::class) {
                $existingLike = CommentReplyLike::where('profile_id', $actor->id)
                    ->where('comment_id', $objectModel->id)
                    ->first();

                if ($existingLike) {
                    if (config('logging.dev_log')) {
                        Log::info('Like already exists', [
                            'actor' => $actor->username,
                            'object_id' => $objectModel->id,
                            'object_type' => $objectClass,
                        ]);
                    }

                    DB::commit();

                    return $existingLike;
                }
                $like = $this->createCommentReplyLike($actor, $objectModel, $activity);

                $this->updateCommentReplyLikeCount($objectModel);
            }

            DB::commit();

            if (config('logging.dev_log')) {
                Log::info('Successfully handled Like activity', [
                    'actor' => $actor->username,
                    'activity_id' => $activity['id'] ?? 'unknown',
                ]);
            }

            return $like;

        } catch (\Exception $e) {
            DB::rollBack();

            if (config('logging.dev_log')) {
                Log::error('Failed to handle Like activity', [
                    'actor' => $actor->username,
                    'object' => $objectUrl,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }

            throw $e;
        }
    }

    private function findLocalStatus(string $url): Video|Comment|CommentReply|false
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
                return Video::published()->whereProfileId($statusMatch['userId'])->whereKey($statusMatch['videoId'])->first();
            }

            $videoHashIdMatch = app(SanitizeService::class)->matchUrlTemplate(
                url: $url,
                templates: ['/v/{hashId}'],
                useAppHost: true,
                constraints: ['hashId' => '[0-9a-zA-Z_-]{1,11}']
            );

            if ($videoHashIdMatch && isset($videoHashIdMatch['hashId'])) {
                $decodedId = HashidService::safeDecode($videoHashIdMatch['hashId']);
                if ($decodedId !== null) {
                    return Video::published()->whereKey($decodedId)->first();
                }
            }

            $commentMatch = app(SanitizeService::class)->matchUrlTemplate(
                url: $url,
                templates: [
                    '/ap/users/{userId}/comment/{replyId}',
                ],
                useAppHost: true,
                constraints: ['userId' => '\d+', 'replyId' => '\d+']
            );

            if ($commentMatch && isset($commentMatch['userId'], $commentMatch['replyId'])) {
                return Comment::whereProfileId($commentMatch['userId'])->whereKey($commentMatch['replyId'])->first();
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

            $video = Video::where('ap_id', $url)->orWhere('uri', $url)->first();
            if ($video) {
                return $video;
            }
        }

        return false;
    }

    private function createVideoLike(Profile $actor, Video $video, array $activity): VideoLike
    {
        $like = VideoLike::firstOrCreate([
            'profile_id' => $actor->id,
            'video_id' => $video->id,
        ]);

        return $like;
    }

    private function createCommentLike(Profile $actor, Comment $comment, array $activity): CommentLike
    {
        $like = CommentLike::firstOrCreate([
            'profile_id' => $actor->id,
            'comment_id' => $comment->id,
        ]);

        return $like;
    }

    private function createCommentReplyLike(Profile $actor, CommentReply $comment, array $activity): CommentReplyLike
    {
        $like = CommentReplyLike::firstOrCreate([
            'profile_id' => $actor->id,
            'comment_id' => $comment->id,
        ]);

        return $like;
    }

    private function updateVideoLikeCount(Video $video): void
    {
        $video->increment('likes');
    }

    private function updateCommentLikeCount(Comment $comment): void
    {
        $comment->increment('likes');
    }

    private function updateCommentReplyLikeCount(CommentReply $comment): void
    {
        $comment->increment('likes');
    }
}
