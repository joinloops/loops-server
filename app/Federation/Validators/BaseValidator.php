<?php

namespace App\Federation\Validators;

use App\Models\Comment;
use App\Models\CommentReply;
use App\Models\Profile;
use App\Models\Video;
use App\Services\SanitizeService;

abstract class BaseValidator
{
    abstract public function validate(array $activity): void;

    protected function hasRequiredFields(array $activity, array $fields): bool
    {
        foreach ($fields as $field) {
            if (! isset($activity[$field])) {
                return false;
            }
        }

        return true;
    }

    public function localDomain(): string
    {
        $app = parse_url(config('app.url'));
        $appHost = strtolower(data_get($app, 'host'));

        return $appHost;
    }

    public function isLocalObject(string $url): bool
    {
        $u = parse_url($url);

        if (! $u) {
            return false;
        }

        if ($u['scheme'] != 'https') {
            return false;
        }

        $host = strtolower(data_get($u, 'host'));
        $appHost = $this->localDomain();

        if ($host === $appHost) {
            return true;
        }

        return false;
    }

    public function isLocalProfile(string $url): bool
    {
        $isLocal = $this->isLocalObject($url);

        if (! $isLocal) {
            return false;
        }

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
                return Profile::where('id', $profileMatch['profileId'])->whereLocal(true)->exists();
            }

            if (isset($profileMatch['username'])) {
                return Profile::where('username', $profileMatch['username'])->whereLocal(true)->exists();
            }
        }

        return false;
    }

    /**
     * Check if the given URL represents a local status
     */
    public function isLocalStatus(string $url): bool
    {
        $isLocal = $this->isLocalObject($url);

        // Return false immediately for non-local URLs
        if (!$isLocal) {
            return false; 
        }

        $statusMatch = app(SanitizeService::class)->matchUrlTemplate(
            url: $url,
            templates: [
                '/ap/users/{userId}/video/{videoId}',
            ],
            useAppHost: true,
            constraints: ['userId' => '\d+', 'videoId' => '\d+']
        );

        if ($statusMatch && isset($statusMatch['userId'], $statusMatch['videoId'])) {
            return Video::whereProfileId($statusMatch['userId'])->whereKey($statusMatch['videoId'])->exists();
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
            return Comment::whereProfileId($commentMatch['userId'])->whereKey($commentMatch['replyId'])->exists();
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
            return CommentReply::whereProfileId($commentReplyMatch['userId'])->whereKey($commentReplyMatch['commentReplyId'])->exists();
        }

        return false;
    }
}
