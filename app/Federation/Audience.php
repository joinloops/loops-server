<?php

namespace App\Federation;

class Audience
{
    const VISIBILITY_PUBLIC = 1;

    const VISIBILITY_LOCAL_ONLY = 2;

    const VISIBILITY_UNLISTED = 3;

    const VISIBILITY_FOLLOWERS_ONLY = 4;

    const VISIBILITY_MENTIONED_ONLY = 5;

    const PUBLIC_COLLECTION = 'https://www.w3.org/ns/activitystreams#Public';

    // Some instances use this for local-only posts (Mastodon extension)
    const LOCAL_ONLY_COLLECTION = 'as:Public';

    /**
     * Determine visibility from ActivityPub to/cc fields
     *
     * @param  array  $to  Array of recipient URIs
     * @param  array  $cc  Array of carbon copy recipient URIs
     * @param  string|null  $followersUrl  The actor's followers collection URL (e.g., https://example.com/users/alice/followers)
     * @return int Visibility level (1-5)
     */
    public static function determineVisibility(array $to, array $cc, ?string $followersUrl = null): int
    {
        $to = array_filter($to, 'is_string');
        $cc = array_filter($cc, 'is_string');

        $to = array_map('strtolower', $to);
        $cc = array_map('strtolower', $cc);

        $publicCollection = strtolower(self::PUBLIC_COLLECTION);
        $localOnlyCollection = strtolower(self::LOCAL_ONLY_COLLECTION);
        $followersUrlLower = $followersUrl ? strtolower($followersUrl) : null;

        // as:Public is a standard CURIE for the Public collection, treat it as public
        $isPublicTo = in_array($publicCollection, $to) || in_array($localOnlyCollection, $to);
        $isPublicCc = in_array($publicCollection, $cc) || in_array($localOnlyCollection, $cc);

        // Public: Public collection in 'to'
        if ($isPublicTo) {
            return self::VISIBILITY_PUBLIC;
        }

        // Unlisted: Public collection in 'cc' but not in 'to'
        if ($isPublicCc) {
            return self::VISIBILITY_UNLISTED;
        }

        // Followers-only: followers collection in 'to' or 'cc', but no public collection
        if ($followersUrlLower && (in_array($followersUrlLower, $to) || in_array($followersUrlLower, $cc))) {
            return self::VISIBILITY_FOLLOWERS_ONLY;
        }

        // Mentioned only (direct): No public collection, no followers collection
        // Just specific user mentions
        if (! empty($to) || ! empty($cc)) {
            return self::VISIBILITY_MENTIONED_ONLY;
        }

        // Default to mentioned only if we can't determine
        return self::VISIBILITY_MENTIONED_ONLY;
    }

    /**
     * Convert visibility level to ActivityPub to/cc fields
     *
     * @param  int  $visibility  Visibility level (1-5)
     * @param  string  $followersUrl  The actor's follower URI (e.g., https://example.com/users/alice)
     * @param  array  $mentionedUsers  Array of mentioned user URIs
     * @return array ['to' => [...], 'cc' => [...]]
     */
    public static function getAudience(int $visibility, string $followersUrl, array $mentionedUsers = []): array
    {
        switch ($visibility) {
            case self::VISIBILITY_PUBLIC:
                // Public: to=[Public], cc=[followers + mentions]
                return [
                    'to' => [self::PUBLIC_COLLECTION],
                    'cc' => array_merge([$followersUrl], $mentionedUsers),
                ];

            case self::VISIBILITY_LOCAL_ONLY:
                // Local only: Similar to public but with local-only marker
                // Different implementations handle this differently
                return [
                    'to' => [self::LOCAL_ONLY_COLLECTION],
                    'cc' => array_merge([$followersUrl], $mentionedUsers),
                ];

            case self::VISIBILITY_UNLISTED:
                // Unlisted: to=[followers + mentions], cc=[Public]
                return [
                    'to' => array_merge([$followersUrl], $mentionedUsers),
                    'cc' => [self::PUBLIC_COLLECTION],
                ];

            case self::VISIBILITY_FOLLOWERS_ONLY:
                // Followers only: to=[followers + mentions], cc=[]
                return [
                    'to' => array_merge([$followersUrl], $mentionedUsers),
                    'cc' => [],
                ];

            case self::VISIBILITY_MENTIONED_ONLY:
                // Direct/Mentioned only: to=[mentions], cc=[]
                return [
                    'to' => $mentionedUsers,
                    'cc' => [],
                ];

            default:
                // Default to mentioned only for safety
                return [
                    'to' => $mentionedUsers,
                    'cc' => [],
                ];
        }
    }

    /**
     * Extract mentioned users from ActivityPub object content
     * Looks for links with rel="mention" or @-mentions in the content
     *
     * @param  object|array  $activity  The ActivityPub activity or object
     * @return array Array of mentioned user URIs
     */
    public static function extractMentions($activity): array
    {
        $mentions = [];

        // Convert to array if object
        if (is_object($activity)) {
            $activity = json_decode(json_encode($activity), true);
        }

        // Check 'tag' field for Mention types
        if (isset($activity['tag']) && is_array($activity['tag'])) {
            foreach ($activity['tag'] as $tag) {
                if (isset($tag['type']) && $tag['type'] === 'Mention' && isset($tag['href'])) {
                    $mentions[] = $tag['href'];
                }
            }
        }

        // Also check object.tag if this is a nested structure
        if (isset($activity['object']['tag']) && is_array($activity['object']['tag'])) {
            foreach ($activity['object']['tag'] as $tag) {
                if (isset($tag['type']) && $tag['type'] === 'Mention' && isset($tag['href'])) {
                    $mentions[] = $tag['href'];
                }
            }
        }

        return array_unique($mentions);
    }

    /**
     * Get visibility name from constant
     */
    public static function getVisibilityName(int $visibility): string
    {
        return match ($visibility) {
            self::VISIBILITY_PUBLIC => 'public',
            self::VISIBILITY_LOCAL_ONLY => 'local_only',
            self::VISIBILITY_UNLISTED => 'unlisted',
            self::VISIBILITY_FOLLOWERS_ONLY => 'followers_only',
            self::VISIBILITY_MENTIONED_ONLY => 'mentioned_only',
            default => 'unknown',
        };
    }
}
