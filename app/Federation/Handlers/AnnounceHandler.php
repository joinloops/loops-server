<?php

namespace App\Federation\Handlers;

use App\Models\Comment;
use App\Models\CommentReply;
use App\Models\CommentReplyRepost;
use App\Models\CommentRepost;
use App\Models\InstanceActor;
use App\Models\Profile;
use App\Models\UserFilter;
use App\Models\Video;
use App\Models\VideoRepost;
use App\Services\ActivityService;
use App\Services\NotificationService;
use App\Services\SanitizeService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AnnounceHandler extends BaseHandler
{
    public function handle(array $activity, Profile|InstanceActor $actor, ?Profile $target = null)
    {
        if ($actor instanceof InstanceActor) {
            return $this->handleInstanceAnnounce($activity, $actor, $target);
        }

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

                if ((string) $actor->id !== (string) $video->profile_id) {
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

                if ((string) $actor->id !== (string) $comment->profile_id) {
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

                if ((string) $actor->id !== (string) $reply->profile_id) {
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

    private function handleInstanceAnnounce(
        array $activity,
        InstanceActor $actor,
        ?Profile $target = null
    ) {
        $object = $activity['object'] ?? null;

        if (! is_string($object) && ! is_array($object)) {
            return;
        }

        if (
            is_string($object) &&
            app(SanitizeService::class)->isLocalObject($object)
        ) {
            if (config('logging.dev_log')) {
                Log::info('Ignored relay echo of local object', [
                    'actor' => $actor->uri,
                    'activity_id' => $activity['id'] ?? null,
                    'object' => $object,
                ]);
            }

            return;
        }

        $objectData = is_array($object)
            ? $object
            : app(ActivityService::class)
                ->fetchRemoteActivity($object);

        if (! is_array($objectData)) {
            if (config('logging.dev_log')) {
                Log::warning('Could not fetch relay announced object', [
                    'actor' => $actor->uri,
                    'activity_id' => $activity['id'] ?? null,
                    'object' => $object,
                ]);
            }

            return;
        }

        if (($objectData['type'] ?? null) === 'Create') {
            return $this->processRelayedCreate(
                $objectData,
                $target
            );
        }

        return $this->processRelayedObject(
            $objectData,
            $target
        );
    }

    private function processRelayedCreate(
        array $create,
        ?Profile $target = null
    ) {
        $object = $create['object'] ?? null;

        if (is_string($object)) {
            $objectUrl = $object;

            try {
                $object = app(ActivityService::class)
                    ->fetchRemoteActivity($objectUrl);
            } catch (\Throwable $exception) {
                if (config('logging.dev_log')) {
                    Log::warning('Failed to fetch relayed Create object', [
                        'activity_id' => $create['id'] ?? null,
                        'object' => $objectUrl,
                        'error' => $exception->getMessage(),
                    ]);
                }

                return null;
            }
        }

        if (! is_array($object)) {
            if (config('logging.dev_log')) {
                Log::warning('Relayed Create does not contain a valid object', [
                    'activity_id' => $create['id'] ?? null,
                ]);
            }

            return null;
        }

        $actorReference = $create['actor']
            ?? $object['attributedTo']
            ?? $object['actor']
            ?? null;

        [$actorUri, $actorData] = $this->extractRelayedActor(
            $actorReference
        );

        if (! $actorUri) {
            if (config('logging.dev_log')) {
                Log::warning('Could not determine actor for relayed Create', [
                    'activity_id' => $create['id'] ?? null,
                    'object_id' => $object['id'] ?? null,
                ]);
            }

            return null;
        }

        if (app(SanitizeService::class)->isLocalObject($actorUri)) {
            if (config('logging.dev_log')) {
                Log::info('Ignored relayed Create attributed to local actor', [
                    'activity_id' => $create['id'] ?? null,
                    'actor' => $actorUri,
                ]);
            }

            return null;
        }

        try {
            $originalActor = app(Profile::class)->findOrCreateFromUrl(
                url: $actorUri,
                actorData: $actorData,
            );
        } catch (\Throwable $exception) {
            if (config('logging.dev_log')) {
                Log::warning('Failed to resolve actor for relayed Create', [
                    'activity_id' => $create['id'] ?? null,
                    'actor' => $actorUri,
                    'error' => $exception->getMessage(),
                ]);
            }

            return null;
        }

        if (! $originalActor || $originalActor->local) {
            if (config('logging.dev_log')) {
                Log::warning('Relayed Create actor could not be resolved', [
                    'activity_id' => $create['id'] ?? null,
                    'actor' => $actorUri,
                ]);
            }

            return null;
        }

        $objectId = isset($object['id']) && is_string($object['id'])
            ? $object['id']
            : null;

        if (
            ! isset($create['id']) ||
            ! is_string($create['id']) ||
            $create['id'] === ''
        ) {
            if (! $objectId) {
                if (config('logging.dev_log')) {
                    Log::warning('Relayed Create and object are both missing IDs', [
                        'actor' => $actorUri,
                    ]);
                }

                return null;
            }

            $create['id'] = $objectId.'#relay-create';
        }

        $create['type'] = 'Create';
        $create['actor'] = $originalActor->uri;
        $create['object'] = $object;

        $create['to'] = $create['to']
            ?? $object['to']
            ?? ['https://www.w3.org/ns/activitystreams#Public'];

        $create['cc'] = $create['cc']
            ?? $object['cc']
            ?? [];

        if (
            ! isset($create['audience']) &&
            isset($object['audience'])
        ) {
            $create['audience'] = $object['audience'];
        }

        if (
            ! isset($create['published']) &&
            isset($object['published'])
        ) {
            $create['published'] = $object['published'];
        }

        if (config('logging.dev_log')) {
            Log::info('Processing relayed Create as original actor', [
                'activity_id' => $create['id'],
                'object_id' => $objectId,
                'actor' => $originalActor->uri,
                'target' => $target?->id,
            ]);
        }

        return app(ActivityService::class)->processIncomingActivity(
            $create,
            $originalActor,
            $target
        );
    }

    private function processRelayedObject(
        array $object,
        ?Profile $target = null
    ) {
        if (($object['type'] ?? null) === 'Create') {
            return $this->processRelayedCreate(
                $object,
                $target
            );
        }

        $objectId = $object['id'] ?? null;

        if (! is_string($objectId) || $objectId === '') {
            if (config('logging.dev_log')) {
                Log::warning('Relayed object is missing its canonical ID', [
                    'type' => $object['type'] ?? null,
                ]);
            }

            return null;
        }

        $actorReference = $object['attributedTo']
            ?? $object['actor']
            ?? null;

        [$actorUri] = $this->extractRelayedActor(
            $actorReference
        );

        if (! $actorUri) {
            if (config('logging.dev_log')) {
                Log::warning('Could not determine actor for relayed object', [
                    'object_id' => $objectId,
                    'type' => $object['type'] ?? null,
                ]);
            }

            return null;
        }

        $create = [
            '@context' => $object['@context']
                ?? 'https://www.w3.org/ns/activitystreams',

            'id' => $objectId.'#relay-create',
            'type' => 'Create',
            'actor' => $actorUri,
            'published' => $object['published'] ?? now()->toIso8601ZuluString(),

            'to' => $object['to']
                ?? ['https://www.w3.org/ns/activitystreams#Public'],

            'cc' => $object['cc'] ?? [],
            'object' => $object,
        ];

        if (isset($object['audience'])) {
            $create['audience'] = $object['audience'];
        }

        return $this->processRelayedCreate(
            $create,
            $target
        );
    }

    private function extractRelayedActor(mixed $reference): array
    {
        if (is_string($reference) && trim($reference) !== '') {
            return [trim($reference), null];
        }

        if (! is_array($reference)) {
            return [null, null];
        }

        if (
            isset($reference['id']) &&
            is_string($reference['id']) &&
            trim($reference['id']) !== ''
        ) {
            return [
                trim($reference['id']),
                $reference,
            ];
        }

        foreach ($reference as $actor) {
            if (is_string($actor) && trim($actor) !== '') {
                return [trim($actor), null];
            }

            if (
                is_array($actor) &&
                isset($actor['id']) &&
                is_string($actor['id']) &&
                trim($actor['id']) !== ''
            ) {
                return [
                    trim($actor['id']),
                    $actor,
                ];
            }
        }

        return [null, null];
    }
}
