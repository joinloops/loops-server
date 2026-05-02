<?php

namespace App\Federation\Validators;

use App\Models\Instance;
use App\Models\Video;
use App\Services\SanitizeService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class NoteWithVideoAttachmentValidator extends BaseValidator
{
    /**
     * Validates a Note activity.
     *
     * @throws \Exception if the activity is invalid.
     */
    public function validate(array $activity): void
    {
        if (! $this->hasRequiredFields($activity, ['id', 'type', 'attributedTo', 'attachment'])) {
            throw new \Exception('Note activity is missing required fields: id, type, actor, attachment, or object.');
        }

        if ($activity['type'] !== 'Note') {
            throw new \Exception("Invalid activity type: expected 'Note', got '{$activity['type']}'.");
        }

        if (! app(SanitizeService::class)->url($activity['attributedTo'], true)) {
            throw new \Exception('Create activity "attributedTo" URI is invalid.');
        }

        if (app(SanitizeService::class)->isLocalObject($activity['id'])) {
            throw new \Exception('Invalid note activity origin.');
        }

        $hasVideoAttachment = $this->hasVideoAttachment($activity);
        if (! $hasVideoAttachment) {
            throw new \Exception("'Note' activity must have a video attachment.");
        }

        $hasInReplyTo = ! empty($activity['inReplyTo']);

        if ($hasInReplyTo) {
            throw new \Exception("'Note' activity cannot have both video attachment and inReplyTo.");
        }

        $this->validateVideoPost($activity['attributedTo'], $activity);
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
}
