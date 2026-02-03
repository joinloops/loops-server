<?php

namespace App\Federation\Handlers;

use App\Federation\Audience;
use App\Jobs\Federation\ProcessRemoteVideoJob;
use App\Models\Comment;
use App\Models\CommentReply;
use App\Models\Profile;
use App\Models\UserFilter;
use App\Models\Video;
use App\Services\HashidService;
use App\Services\SanitizeService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CreateHandler extends BaseHandler
{
    public function handle(array $activity, Profile $actor, Profile $target)
    {
        $object = $activity['object'];

        $targetIsBlocking = UserFilter::whereProfileId($target->id)->whereAccountId($actor->id)->exists();

        if ($targetIsBlocking) {
            if (config('logging.dev_log')) {
                Log::info('Target is blocking actor', [
                    'actor' => $actor->id,
                    'target' => $target->id,
                ]);
            }

            return;
        }

        try {
            DB::beginTransaction();

            $hasInReplyTo = ! empty($object['inReplyTo']);
            $hasVideoAttachment = $this->hasVideoAttachment($object);

            if ($hasVideoAttachment && ! $hasInReplyTo) {
                $result = $this->createVideoPost($object, $actor);
            } elseif ($hasInReplyTo && ! $hasVideoAttachment) {
                $result = $this->createReply($object, $actor, $object['inReplyTo']);
            } else {
                throw new \Exception('Invalid Create activity: must have either video attachment or inReplyTo, but not both.');
            }

            DB::commit();

            if (config('logging.dev_log')) {
                Log::info('Successfully handled Create activity', [
                    'actor' => $actor->username,
                    'object_id' => $object['id'] ?? 'unknown',
                    'type' => $hasVideoAttachment ? 'video_post' : 'reply',
                    'created_type' => is_array($result) ? json_encode($result) : get_class($result),
                ]);
            }

            return $result;

        } catch (\Exception $e) {
            DB::rollBack();

            if (config('logging.dev_log')) {
                Log::error('Failed to handle Create activity', [
                    'actor' => $actor->username,
                    'object_id' => $object['id'] ?? 'unknown',
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }

            throw $e;
        }
    }

    /**
     * Check if the object has a valid video attachment
     */
    private function hasVideoAttachment(array $object): bool
    {
        if (empty($object['attachment']) || ! is_array($object['attachment'])) {
            return false;
        }

        foreach ($object['attachment'] as $attachment) {
            if (! is_array($attachment)) {
                continue;
            }

            if ($this->isValidVideoAttachment($attachment)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if an attachment is a valid video
     */
    private function isValidVideoAttachment(array $attachment): bool
    {
        if (empty($attachment['type']) || ! in_array($attachment['type'], ['Document', 'Video'])) {
            return false;
        }

        if (empty($attachment['mediaType']) || $attachment['mediaType'] !== 'video/mp4') {
            return false;
        }

        if (empty($attachment['url']) || ! is_string($attachment['url'])) {
            return false;
        }

        return true;
    }

    /**
     * Extract video attachment from object
     */
    private function extractVideoAttachment(array $object): ?array
    {
        if (empty($object['attachment']) || ! is_array($object['attachment'])) {
            return null;
        }

        foreach ($object['attachment'] as $attachment) {
            if (! is_array($attachment)) {
                continue;
            }

            if ($this->isValidVideoAttachment($attachment)) {
                return $attachment;
            }
        }

        return null;
    }

    /**
     * Create a video post from a remote Note with video attachment
     */
    private function createVideoPost(array $object, Profile $actor)
    {
        if (isset($object['id'])) {
            $existing = Video::where('ap_id', $object['id'])->exists();
            if ($existing) {
                return $existing;
            }
        }

        $videoAttachment = $this->extractVideoAttachment($object);
        if (! $videoAttachment) {
            throw new \Exception('No valid video attachment found in Create activity.');
        }

        ProcessRemoteVideoJob::dispatch($actor->id, $object, $videoAttachment)->onQueue('remote-video');

        if (config('logging.dev_log')) {
            Log::info('Dispatched remote video processing job', [
                'actor' => $actor->username,
                'object_id' => $object['id'] ?? 'unknown',
                'remote_url' => $videoAttachment['url'],
            ]);
        }

        return [
            'status' => 'processing',
            'message' => 'Video post accepted and queued for processing',
        ];
    }

    private function createReply(array $object, Profile $actor, string $inReplyToUrl)
    {
        $replyUrl = parse_url($inReplyToUrl);
        $baseDomain = $this->localDomain();
        $isLocal = $this->isLocalObject($inReplyToUrl);

        if (isset($replyUrl['host']) && $replyUrl['host'] == $baseDomain) {
            $isLocal = true;

            $videoHashIdMatch = app(SanitizeService::class)->matchUrlTemplate(
                url: $inReplyToUrl,
                templates: ['/v/{hashId}'],
                useAppHost: true,
                constraints: ['hashId' => '[0-9a-zA-Z_-]{1,11}']
            );

            if ($videoHashIdMatch && isset($videoHashIdMatch['hashId'])) {
                $decodedId = HashidService::safeDecode($videoHashIdMatch['hashId']);
                if ($decodedId !== null) {
                    $video = Video::whereStatus(2)->whereKey($decodedId)->first();
                    if ($video) {
                        return $this->createComment($object, $actor, $video);
                    }
                }
            }

            $videoMatch = app(SanitizeService::class)->matchUrlTemplate(
                url: $inReplyToUrl,
                templates: ['/ap/users/{profileId}/video/{videoId}'],
                useAppHost: true,
                constraints: ['profileId' => '\d+', 'videoId' => '\d+']
            );
            if ($videoMatch && isset($videoMatch['profileId'], $videoMatch['videoId'])) {
                $video = Video::whereStatus(2)
                    ->whereProfileId($videoMatch['profileId'])
                    ->whereKey($videoMatch['videoId'])
                    ->first();
                if ($video) {
                    return $this->createComment($object, $actor, $video);
                }
            }

            $commentMatch = app(SanitizeService::class)->matchUrlTemplate(
                url: $inReplyToUrl,
                templates: ['/ap/users/{profileId}/comment/{commentId}'],
                useAppHost: true,
                constraints: ['profileId' => '\d+', 'commentId' => '\d+']
            );

            if ($commentMatch && isset($commentMatch['profileId'], $commentMatch['commentId'])) {
                $comment = Comment::whereProfileId($commentMatch['profileId'])->whereKey($commentMatch['commentId'])->first();
                if ($comment) {
                    return $this->createComment($object, $actor, $comment->video);
                }
            }

            $replyMatch = app(SanitizeService::class)->matchUrlTemplate(
                url: $inReplyToUrl,
                templates: ['/ap/users/{profileId}/reply/{replyId}'],
                useAppHost: true,
                constraints: ['profileId' => '\d+', 'replyId' => '\d+']
            );

            if ($replyMatch && isset($replyMatch['profileId'], $replyMatch['replyId'])) {
                $commentReply = CommentReply::whereProfileId($replyMatch['profileId'])->whereKey($replyMatch['replyId'])->first();
                if ($commentReply) {
                    return $this->createCommentReply($object, $actor, $commentReply->video_id, $commentReply->comment_id);
                }
            }
        }

        if (! $isLocal) {
            $video = Video::where('ap_id', $inReplyToUrl)->first();
            if ($video) {
                return $this->createComment($object, $actor, $video);
            }

            $comment = Comment::where('ap_id', $inReplyToUrl)->first();
            if ($comment) {
                return $this->createComment($object, $actor, $comment->video);
            }

            $commentReply = CommentReply::where('ap_id', $inReplyToUrl)->first();
            if ($commentReply) {
                return $this->createCommentReply($object, $actor, $commentReply->video_id, $commentReply->comment_id);
            }
        }

        throw new \Exception("Could not find local target for inReplyTo: {$inReplyToUrl}");
    }

    private function createComment(array $object, Profile $actor, Video $video): Comment
    {
        if (isset($object['id'])) {
            $existing = Comment::where('ap_id', $object['id'])->first();
            if ($existing) {
                return $existing;
            }
        }

        $visibility = $this->determineVisibilityFromObject($object, $actor);

        $comment = new Comment;
        $comment->video_id = $video->id;
        $comment->profile_id = $actor->id;
        $comment->caption = $this->extractContent($object);
        $comment->visibility = $visibility;
        $comment->status = 'active';
        $comment->ap_id = $object['id'] ?? null;
        $comment->created_at = $this->extractPublishedDate($object);
        $comment->updated_at = now();

        if (isset($object['url'])) {
            $comment->remote_url = data_get($object, 'url');
        }

        $comment->save();
        $comment->syncHashtagsFromCaption();
        $comment->syncMentionsFromCaption();

        return $comment;
    }

    private function createCommentReply(array $object, Profile $actor, int $videoId, int $commentId): CommentReply
    {
        if (isset($object['id'])) {
            $existing = CommentReply::where('ap_id', $object['id'])->first();
            if ($existing) {
                return $existing;
            }
        }

        $visibility = $this->determineVisibilityFromObject($object, $actor);

        $reply = new CommentReply;
        $reply->video_id = $videoId;
        $reply->comment_id = $commentId;
        $reply->profile_id = $actor->id;
        $reply->caption = $this->extractContent($object, 'content');
        $reply->visibility = $visibility;
        $reply->status = 'active';
        $reply->ap_id = $object['id'] ?? null;
        $reply->created_at = $this->extractPublishedDate($object);
        $reply->updated_at = now();

        if (isset($object['url'])) {
            $reply->remote_url = data_get($object, 'url');
        }

        $reply->save();
        $reply->syncHashtagsFromCaption();
        $reply->syncMentionsFromCaption();

        return $reply;
    }

    /**
     * Determine visibility from ActivityPub object's to/cc fields
     */
    private function determineVisibilityFromObject(array $object, Profile $actor): int
    {
        $to = $object['to'] ?? [];
        $cc = $object['cc'] ?? [];

        if (! is_array($to)) {
            $to = [$to];
        }
        if (! is_array($cc)) {
            $cc = [$cc];
        }

        $followersUrl = $actor->getFollowersUrl();

        $visibility = Audience::determineVisibility($to, $cc, $followersUrl);

        if (config('logging.dev_log')) {
            Log::info('Determined visibility for incoming activity', [
                'actor' => $actor->username,
                'visibility' => Audience::getVisibilityName($visibility),
                'to' => $to,
                'cc' => $cc,
            ]);
        }

        return $visibility;
    }

    private function extractContent(array $object, $key = false): string
    {
        if ($key && isset($object[$key])) {
            return app(SanitizeService::class)->cleanHtmlWithSpacing($object[$key]);
        }

        if (isset($object['content'])) {
            return app(SanitizeService::class)->cleanHtmlWithSpacing($object['content']);
        }

        if (isset($object['summary'])) {
            return app(SanitizeService::class)->cleanHtmlWithSpacing($object['summary']);
        }

        if (isset($object['name'])) {
            return app(SanitizeService::class)->cleanHtmlWithSpacing($object['name']);
        }

        return '';
    }

    private function extractPublishedDate(array $object): Carbon
    {
        if (isset($object['published'])) {
            try {
                return Carbon::parse($object['published']);
            } catch (\Exception $e) {
                Log::warning('Failed to parse published date', [
                    'published' => $object['published'],
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return now();
    }
}
