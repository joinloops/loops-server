<?php

namespace App\Federation\Validators;

use App\Models\Comment;
use App\Models\CommentReply;
use App\Models\Instance;
use App\Models\Video;
use App\Services\ActivityPubService;
use App\Services\HashidService;
use App\Services\SanitizeService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CreateValidator extends BaseValidator
{
    /**
     * Validates an incoming Create activity.
     * Handles both replies (Notes with inReplyTo) and video posts (Notes with video attachments).
     *
     * @throws \Exception if the activity is invalid.
     */
    public function validate(array $activity): void
    {
        if (! $this->hasRequiredFields($activity, ['id', 'type', 'actor', 'object'])) {
            throw new \Exception('Create activity is missing required fields: id, type, actor, or object.');
        }

        if ($activity['type'] !== 'Create') {
            throw new \Exception("Invalid activity type: expected 'Create', got '{$activity['type']}'.");
        }

        if (! is_array($activity['object'])) {
            throw new \Exception('Create activity "object" must be an array.');
        }

        if (! app(SanitizeService::class)->url($activity['actor'], true)) {
            throw new \Exception('Create activity "actor" URI is invalid.');
        }

        $object = $activity['object'];

        if (app(SanitizeService::class)->isLocalObject($object['id'])) {
            throw new \Exception('Invalid create activity origin.');
        }

        if (empty($object['type']) || $object['type'] !== 'Note') {
            throw new \Exception("Create activity 'object' type must be 'Note'. Got '{$object['type']}'.");
        }

        $hasVideoAttachment = $this->hasVideoAttachment($object);
        $hasInReplyTo = ! empty($object['inReplyTo']);

        if ($hasVideoAttachment && $hasInReplyTo) {
            throw new \Exception("Create 'Note' activity cannot have both video attachment and inReplyTo.");
        }

        if (! $hasVideoAttachment && ! $hasInReplyTo) {
            throw new \Exception("Create 'Note' activity must have either a video attachment or inReplyTo property.");
        }

        if ($hasVideoAttachment) {
            $this->validateVideoPost($activity['actor'], $object);
        } elseif ($hasInReplyTo) {
            $this->validateReply($object);
        }
    }

    /**
     * Validates a reply Note.
     *
     * @throws \Exception if the reply is invalid.
     */
    private function validateReply(array $object): void
    {
        if (! is_string($object['inReplyTo'])) {
            throw new \Exception("Create 'Note' activity 'inReplyTo' property must be a string URI.");
        }

        $this->validateReplyChain($object['inReplyTo']);
    }

    /**
     * Validates a video post Note.
     *
     * @throws \Exception if the video post is invalid.
     */
    private function validateVideoPost(string $actorUrl, array $object): void
    {
        $actorDomain = parse_url($actorUrl, PHP_URL_HOST);

        if (! $actorDomain) {
            throw new \Exception("Unable to extract domain from actor URL: '{$actorUrl}'.");
        }

        $instance = Instance::where('domain', $actorDomain)->first();

        if (! $instance) {
            throw new \Exception("Unknown instance '{$actorDomain}'. Video posts are only accepted from known instances.");
        }

        if (! $instance->allow_video_posts) {
            throw new \Exception("Instance '{$actorDomain}' is not allowed to post videos.");
        }

        if (empty($object['attachment']) || ! is_array($object['attachment'])) {
            throw new \Exception("Video post must have valid 'attachment' array.");
        }

        $validVideoAttachment = null;
        foreach ($object['attachment'] as $attachment) {
            if (! is_array($attachment)) {
                continue;
            }

            if ($this->isValidVideoAttachment($attachment)) {
                $validVideoAttachment = $attachment;
                break;
            }
        }

        if (! $validVideoAttachment) {
            throw new \Exception("Video post must contain at least one valid video attachment with type 'Document' or 'Video' and mediaType 'video/mp4'.");
        }

        $this->validateVideoSize($validVideoAttachment['url']);
    }

    /**
     * Validate video file size via HEAD request
     *
     * @throws \Exception if the video is too large or unreachable
     */
    private function validateVideoSize(string $url): void
    {
        $maxSizeBytes = 100 * 1024 * 1024;

        try {
            $response = Http::timeout(10)
                ->withHeaders([
                    'User-Agent' => app('user_agent'),
                ])
                ->head($url);

            if (! $response->successful()) {
                throw new \Exception("Unable to verify video file at '{$url}' (HTTP {$response->status()}).");
            }

            $contentLength = $response->header('Content-Length');

            if (! $contentLength) {
                if (config('logging.dev_log')) {
                    Log::warning('Video URL did not return Content-Length header', ['url' => $url]);
                }

                return;
            }

            $fileSize = (int) $contentLength;

            if ($fileSize > $maxSizeBytes) {
                $fileSizeMB = round($fileSize / (1024 * 1024), 2);
                $maxSizeMB = round($maxSizeBytes / (1024 * 1024), 2);
                throw new \Exception("Video file is too large: {$fileSizeMB}MB (max: {$maxSizeMB}MB).");
            }

            if (config('logging.dev_log')) {
                Log::info('Validated video file size', [
                    'url' => $url,
                    'size_bytes' => $fileSize,
                    'size_mb' => round($fileSize / (1024 * 1024), 2),
                ]);
            }

        } catch (\Illuminate\Http\Client\RequestException $e) {
            throw new \Exception("Failed to reach video file at '{$url}': {$e->getMessage()}");
        } catch (\Exception $e) {
            if (str_contains($e->getMessage(), 'too large')) {
                throw $e;
            }
            throw new \Exception("Error validating video file: {$e->getMessage()}");
        }
    }

    /**
     * Check if the Note has a video attachment.
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
     * Check if an attachment is a valid video.
     */
    private function isValidVideoAttachment(array $attachment): bool
    {
        // Check if type is 'Document' or 'Video'
        if (empty($attachment['type']) || ! in_array($attachment['type'], ['Document', 'Video'])) {
            return false;
        }

        // Check if mediaType is 'video/mp4'
        if (empty($attachment['mediaType']) || $attachment['mediaType'] !== 'video/mp4') {
            return false;
        }

        // Check if url is present and valid
        if (empty($attachment['url']) || ! is_string($attachment['url'])) {
            return false;
        }

        if (! app(SanitizeService::class)->url($attachment['url'], true)) {
            return false;
        }

        return true;
    }

    /**
     * Walk the reply chain to ensure it eventually leads to a local Video.
     *
     * @throws \Exception if the chain is invalid or does not lead to a local Video.
     */
    private function validateReplyChain(string $inReplyToUrl, int $depth = 0): void
    {
        if ($depth > 10) {
            throw new \Exception("Reply chain depth exceeded (10) while checking '{$inReplyToUrl}'.");
        }

        if (! app(SanitizeService::class)->url($inReplyToUrl, true)) {
            throw new \Exception("Invalid URL in reply chain: '{$inReplyToUrl}'.");
        }

        $sanitizeService = app(SanitizeService::class);

        if ($this->isLocalObject($inReplyToUrl)) {
            $hashIdMatch = $sanitizeService->matchUrlTemplate(
                url: $inReplyToUrl,
                templates: [
                    '/v/{hashId}',
                ],
                useAppHost: true,
                constraints: ['hashId' => '[0-9a-zA-Z_-]{1,11}']
            );

            if ($hashIdMatch) {
                $decodedId = HashidService::safeDecode($hashIdMatch['hashId']);
                if ($decodedId !== null && Video::where('id', $decodedId)->exists()) {
                    return;
                }
            }

            $videoMatch = $sanitizeService->matchUrlTemplate(
                url: $inReplyToUrl,
                templates: [
                    '/ap/users/{pId}/video/{vId}',
                ],
                useAppHost: true,
                constraints: ['pId' => '\d+', 'vId' => '\d+']
            );

            if ($videoMatch && Video::where('id', $videoMatch['vId'])->exists()) {
                return;
            }

            $commentMatch = $sanitizeService->matchUrlTemplate(
                url: $inReplyToUrl,
                templates: [
                    '/ap/users/{pId}/comment/{cId}',
                ],
                useAppHost: true,
                constraints: ['pId' => '\d+', 'cId' => '\d+']
            );

            if ($commentMatch && Comment::where('id', $commentMatch['cId'])->exists()) {
                return;
            }

            $replyMatch = $sanitizeService->matchUrlTemplate(
                url: $inReplyToUrl,
                templates: [
                    '/ap/users/{pId}/reply/{rId}',
                ],
                useAppHost: true,
                constraints: ['pId' => '\d+', 'rId' => '\d+']
            );

            if ($replyMatch && CommentReply::where('id', $replyMatch['rId'])->exists()) {
                return;
            }

            throw new \Exception("Local object '{$inReplyToUrl}' is not a valid reply target.");
        }

        $comment = Comment::where('ap_id', $inReplyToUrl)->first();
        if ($comment) {
            if ($comment->video()->exists()) {
                return;
            }
            throw new \Exception("Known remote comment '{$inReplyToUrl}' is orphaned (no local video).");
        }

        $commentReply = CommentReply::where('ap_id', $inReplyToUrl)->first();
        if ($commentReply) {
            $parentComment = $commentReply->parent;
            if (! $parentComment) {
                throw new \Exception("Known remote reply '{$inReplyToUrl}' is orphaned (parent comment not found).");
            }
            $this->validateReplyChain($parentComment->getObjectUrl(), $depth + 1);

            return;
        }

        try {
            $remoteObject = app(ActivityPubService::class)->get($inReplyToUrl);

            if (! $remoteObject) {
                throw new \Exception("Failed to fetch remote object '{$inReplyToUrl}'.");
            }

            if (empty($remoteObject['inReplyTo'])) {
                throw new \Exception("Remote object '{$inReplyToUrl}' is not a reply, chain terminated.");
            }

            $this->validateReplyChain($remoteObject['inReplyTo'], $depth + 1);

        } catch (\Exception $e) {
            if (config('logging.dev_log')) {
                Log::error("Error fetching remote object '{$inReplyToUrl}'", ['error' => $e->getMessage()]);
            }
            throw new \Exception("Error during reply chain validation: {$e->getMessage()}");
        }
    }
}
