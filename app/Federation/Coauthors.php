<?php

namespace App\Federation;

use App\Models\Video;
use App\Models\VideoCollaboration;
use App\Services\CollabService;

class Coauthors
{
    public const NS = 'https://joinloops.org/ns#';

    public const AUTHORIZATION_TYPE = 'CoauthorAuthorization';

    public static function contextTerms(): array
    {
        return [
            'loops' => self::NS,
            'coauthors' => [
                '@id' => 'loops:coauthors',
                '@container' => '@set',
            ],
            self::AUTHORIZATION_TYPE => 'loops:'.self::AUTHORIZATION_TYPE,
            'gts' => 'https://gotosocial.org/ns#',
            'interactingObject' => [
                '@id' => 'gts:interactingObject',
                '@type' => '@id',
            ],
            'interactionTarget' => [
                '@id' => 'gts:interactionTarget',
                '@type' => '@id',
            ],
        ];
    }

    public static function extendContext($context): array
    {
        $context = is_array($context) ? $context : [$context];

        foreach ($context as $i => $entry) {
            if (is_array($entry) && ! array_is_list($entry)) {
                $context[$i] = array_merge($entry, self::contextTerms());

                return $context;
            }
        }

        $context[] = self::contextTerms();

        return $context;
    }

    /**
     * @return \Illuminate\Support\Collection<int, VideoCollaboration>
     */
    public static function collaborations(Video $video)
    {
        return app(CollabService::class)->federatableCollaborations($video);
    }

    public static function references(Video $video): array
    {
        return self::collaborations($video)
            ->map(fn (VideoCollaboration $c) => [
                'type' => self::AUTHORIZATION_TYPE,
                'id' => $c->attestationUrl(),
                'attributedTo' => $c->profile->getActorId(),
            ])
            ->values()
            ->all();
    }

    public static function hasHistory(Video $video): bool
    {
        return $video->is_local && VideoCollaboration::where('video_id', $video->id)
            ->where('is_local', true)
            ->whereIn('state', [VideoCollaboration::STATE_APPROVED, VideoCollaboration::STATE_REMOVED])
            ->exists();
    }

    public static function applyToNote(array $object, Video $video, bool $authoritative = false): array
    {
        $collabs = self::collaborations($video);

        if ($collabs->isEmpty()) {
            if ($authoritative && self::hasHistory($video)) {
                $object['coauthors'] = [];
            }

            return $object;
        }

        $object['coauthors'] = self::references($video);

        $extra = [];
        foreach ($collabs as $collab) {
            $extra[] = $collab->profile->getActorId();

            if (in_array((int) $video->visibility, [Audience::VISIBILITY_PUBLIC, Audience::VISIBILITY_UNLISTED, Audience::VISIBILITY_FOLLOWERS_ONLY], true)) {
                $extra[] = $collab->profile->getFollowersUrl();
            }
        }

        $field = (int) $video->visibility === Audience::VISIBILITY_FOLLOWERS_ONLY ? 'to' : 'cc';
        $object[$field] = array_values(array_unique(array_merge($object[$field] ?? [], $extra)));

        return $object;
    }

    public static function authorization(VideoCollaboration $collab): array
    {
        $video = $collab->video;

        return [
            '@context' => [
                'https://www.w3.org/ns/activitystreams',
                self::contextTerms(),
            ],
            'type' => self::AUTHORIZATION_TYPE,
            'id' => $collab->attestationUrl(),
            'attributedTo' => $collab->profile->getActorId(),
            'interactingObject' => $video->getObjectUrl(),
            'interactionTarget' => $video->profile->getActorId(),
            'published' => ($collab->responded_at ?? $collab->created_at)->toIso8601ZuluString(),
        ];
    }

    public static function tombstone(VideoCollaboration $collab): array
    {
        return [
            '@context' => 'https://www.w3.org/ns/activitystreams',
            'id' => $collab->attestationUrl(),
            'type' => 'Tombstone',
            'formerType' => self::AUTHORIZATION_TYPE,
            'deleted' => $collab->updated_at?->toIso8601ZuluString(),
        ];
    }

    public static function revocation(VideoCollaboration $collab, Video $video): array
    {
        $coauthor = $collab->profile;

        return [
            '@context' => [
                'https://www.w3.org/ns/activitystreams',
                self::contextTerms(),
            ],
            'id' => $collab->attestationUrl().'#delete',
            'type' => 'Delete',
            'actor' => $coauthor->getActorId(),
            'to' => [Audience::PUBLIC_COLLECTION],
            'cc' => [$video->profile->getActorId()],
            'object' => [
                'id' => $collab->attestationUrl(),
                'type' => self::AUTHORIZATION_TYPE,
                'interactingObject' => $video->getObjectUrl(),
                'interactionTarget' => $video->profile->getActorId(),
            ],
        ];
    }
}
