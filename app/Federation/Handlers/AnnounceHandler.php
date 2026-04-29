<?php

namespace App\Federation\Handlers;

use App\Models\Comment;
use App\Models\CommentReply;
use App\Models\CommentReplyRepost;
use App\Models\CommentRepost;
use App\Models\Profile;
use App\Models\UserFilter;
use App\Models\Video;
use App\Models\VideoRepost;
use App\Services\NotificationService;
use App\Services\SanitizeService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AnnounceHandler extends BaseHandler
{
    public function handle(array $activity, Profile $actor, ?Profile $target = null)
    {
        $objectUrl = $activity['object'];

        try {
            DB::beginTransaction();

            if ($target) {
                $ownerIsBlocking = UserFilter::whereProfileId($target->id)->whereAccountId($actor->id)->exists();

                if ($ownerIsBlocking) {
                    if (config('logging.dev_log')) {
                        Log::info('Status owner is blocking actor', [
                            'actor' => $actor->username,
                            'object_url' => $objectUrl,
                        ]);
                    }
                    DB::commit();

                    return;
                }
            }

            $modelObject = $this->findLocalStatus($objectUrl);

            if (! $modelObject) {
                DB::commit();

                return;
            }

            $modelClass = get_class($modelObject);
            $share = null;

            if ($modelClass === 'App\Models\Video') {
                $video = $modelObject;

                $existingShare = VideoRepost::where('profile_id', $actor->id)
                    ->where('video_id', $video->id)
                    ->first();

                if ($existingShare) {
                    if (config('logging.dev_log')) {
                        Log::info('Announce already exists', [
                            'actor' => $actor->username,
                            'video_id' => $video->id,
                        ]);
                    }

                    DB::commit();

                    return $existingShare;
                }

                $share = $this->createVideoRepostAnnounce($actor, $modelObject, $activity);
                $this->updateVideoShareCount($modelObject);

                if ($actor->id !== $video->profile_id) {
                    NotificationService::newVideoShare(
                        $video->profile_id,
                        $video->id,
                        $actor->id
                    );
                    NotificationService::clearUnreadCount($video->profile_id);
                }

            } elseif ($modelClass === 'App\Models\Comment') {
                $comment = $modelObject;

                $existingShare = CommentRepost::where('profile_id', $actor->id)
                    ->where('video_id', $comment->video_id)
                    ->where('comment_id', $comment->id)
                    ->first();

                if ($existingShare) {
                    if (config('logging.dev_log')) {
                        Log::info('Announce already exists', [
                            'actor' => $actor->username,
                            'comment_id' => $comment->id,
                        ]);
                    }

                    DB::commit();

                    return $existingShare;
                }

                $share = $this->createCommentRepostAnnounce($actor, $comment, $activity);

                if ($actor->id !== $comment->profile_id) {
                    NotificationService::newVideoCommentShare(
                        $comment->profile_id,
                        $comment->id,
                        $comment->video_id,
                        $actor->id
                    );
                    NotificationService::clearUnreadCount($comment->profile_id);
                }

            } elseif ($modelClass === 'App\Models\CommentReply') {
                $reply = $modelObject;

                $existingShare = CommentReplyRepost::where('profile_id', $actor->id)
                    ->where('video_id', $reply->video_id)
                    ->where('reply_id', $reply->id)
                    ->first();

                if ($existingShare) {
                    if (config('logging.dev_log')) {
                        Log::info('Announce already exists', [
                            'actor' => $actor->username,
                            'reply_id' => $reply->id,
                        ]);
                    }

                    DB::commit();

                    return $existingShare;
                }

                $share = $this->createCommentReplyRepostAnnounce($actor, $reply, $activity);

                if ($actor->id !== $reply->profile_id) {
                    NotificationService::newVideoReplyShare(
                        $reply->profile_id,
                        $reply->id,
                        $reply->video_id,
                        $actor->id
                    );
                    NotificationService::clearUnreadCount($reply->profile_id);
                }
            }

            DB::commit();

            if (config('logging.dev_log')) {
                Log::info('Successfully handled Announce activity', [
                    'actor' => $actor->username,
                    'object_id' => $modelObject->id,
                    'object_class' => $modelClass,
                    'activity_id' => $activity['id'] ?? 'unknown',
                ]);
            }

            return $share;

        } catch (\Exception $e) {
            DB::rollBack();

            if (config('logging.dev_log')) {
                Log::error('Failed to handle Announce activity', [
                    'actor' => $actor->username,
                    'object' => $objectUrl,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }

            throw $e;
        }
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
        }

        return false;
    }

    private function createVideoRepostAnnounce(Profile $actor, Video $video, array $activity): VideoRepost
    {
        $share = VideoRepost::firstOrCreate([
            'profile_id' => $actor->id,
            'video_id' => $video->id,
        ]);

        return $share;
    }

    private function createCommentRepostAnnounce(Profile $actor, Comment $comment, array $activity): CommentRepost
    {
        $share = CommentRepost::firstOrCreate([
            'profile_id' => $actor->id,
            'video_id' => $comment->video_id,
            'comment_id' => $comment->id,
        ]);

        return $share;
    }

    private function createCommentReplyRepostAnnounce(Profile $actor, CommentReply $comment, array $activity): CommentReplyRepost
    {
        $share = CommentReplyRepost::firstOrCreate([
            'profile_id' => $actor->id,
            'video_id' => $comment->video_id,
            'comment_id' => $comment->comment_id,
            'reply_id' => $comment->id,
        ]);

        return $share;
    }

    private function updateVideoShareCount(Video $video): void
    {
        $video->increment('shares');
    }
}
