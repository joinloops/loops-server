<?php

namespace App\Federation\ActivityBuilders;

use App\Federation\Audience;
use App\Federation\Coauthors;
use App\Models\Comment;
use App\Models\CommentReply;
use App\Models\Profile;
use App\Models\Video;
use App\Services\AccountService;
use App\Services\AutoLinkerService;

class UpdateActivityBuilder
{
    /**
     * Build an Update activity for a Profile/Actor
     *
     * @param  Profile  $actor  The profile being updated
     * @return array The ActivityPub Update activity
     */
    public static function buildForProfile(Profile $actor): array
    {
        $activityId = $actor->getActorId('#updates/'.time());

        $actorObject = $actor->toActivityPub();
        $actorObject['updated'] = $actor->updated_at->toIso8601ZuluString();

        return [
            '@context' => [
                'https://www.w3.org/ns/activitystreams',
                'https://w3id.org/security/v1',
            ],
            'id' => $activityId,
            'type' => 'Update',
            'actor' => $actor->getActorId(),
            'published' => now()->toIso8601ZuluString(),
            'to' => [
                'https://www.w3.org/ns/activitystreams#Public',
            ],
            'object' => $actorObject,
        ];
    }

    /**
     * Build an Update activity for a Video
     *
     * @param  Profile  $actor  The profile updating the video
     * @param  Video  $video  The video being updated
     * @return array The ActivityPub Update activity
     */
    public static function buildForVideo(Profile $actor, Video $video): array
    {
        $activityId = $video->getObjectUrl('#updates/'.time());

        $videoObject = [
            'id' => $video->getObjectUrl(),
            'type' => 'Note',
            'content' => $video->caption ? AutoLinkerService::link($video->caption) : null,
            'attributedTo' => $actor->getActorId(),
            'url' => $video->shareUrl(),
            'summary' => null,
            'inReplyTo' => null,
            'sensitive' => $video->is_sensitive,
            'published' => $video->created_at->toIso8601ZuluString(),
            'updated' => $video->updated_at->toIso8601ZuluString(),
            'to' => [],
            'cc' => [],
            'attachment' => [
                [
                    'type' => 'Document',
                    'mediaType' => 'video/mp4',
                    'url' => $video->mediaUrl(),
                    'name' => $video->alt_text,
                ],
            ],
        ];
        $mentions = [];

        if ($video->caption) {
            $videoObject['tag'] = [];

            foreach ($video->hashtags as $hashtag) {
                $videoObject['tag'][] = [
                    'type' => 'Hashtag',
                    'name' => '#'.$hashtag->name,
                    'href' => url("/tag/{$hashtag->name}"),
                ];
            }

            $seenMentions = [];
            foreach ($video->mentions as $mention) {
                if (isset($seenMentions[$mention->profile_id])) {
                    continue;
                }

                $seenMentions[$mention->profile_id] = true;
                $acct = AccountService::compact($mention->profile_id);
                if (empty($acct)) {
                    continue;
                }
                $uri = AccountService::getActorId($mention->profile_id);
                if ($uri) {
                    $videoObject['tag'][] = [
                        'type' => 'Mention',
                        'href' => $uri,
                        'name' => "@{$acct['username']}",
                    ];
                    $mentions[] = $uri;
                }
            }
        }

        $audience = Audience::getAudience($video->visibility, $actor->getFollowersUrl(), $mentions);
        $videoObject['to'] = $audience['to'];
        $videoObject['cc'] = $audience['cc'];

        $videoObject = Coauthors::applyToNote($videoObject, $video, authoritative: true);

        return [
            '@context' => array_key_exists('coauthors', $videoObject)
                ? Coauthors::extendContext('https://www.w3.org/ns/activitystreams')
                : 'https://www.w3.org/ns/activitystreams',
            'id' => $activityId,
            'type' => 'Update',
            'actor' => $actor->getActorId(),
            'published' => now()->toIso8601ZuluString(),
            'to' => $videoObject['to'],
            'cc' => $videoObject['cc'],
            'object' => $videoObject,
        ];
    }

    /**
     * Build an Update activity for a Comment
     *
     * @param  Profile  $actor  The profile updating the video
     * @param  Comment  $comment  The comment being updated
     * @return array The ActivityPub Update activity
     */
    public static function buildForComment(Profile $actor, Comment $comment): array
    {
        $activityId = $comment->getObjectUrl('#updates/'.time());

        $commentObject = [
            'id' => $comment->getObjectUrl(),
            'type' => 'Note',
            'content' => $comment->caption ? AutoLinkerService::link($comment->caption) : null,
            'attributedTo' => $actor->getActorId(),
            'url' => $comment->shareUrl(),
            'summary' => null,
            'inReplyTo' => $comment->video->getObjectUrl(),
            'sensitive' => $comment->is_sensitive,
            'published' => $comment->created_at->toIso8601ZuluString(),
            'updated' => $comment->updated_at->toIso8601ZuluString(),
            'to' => [],
            'cc' => [],
        ];
        $mentions = [];

        if ($comment->caption) {
            $commentObject['tag'] = [];

            foreach ($comment->hashtags as $hashtag) {
                $commentObject['tag'][] = [
                    'type' => 'Hashtag',
                    'name' => '#'.$hashtag->name,
                    'href' => url("/tag/{$hashtag->name}"),
                ];
            }

            $seenMentions = [];
            foreach ($comment->mentions as $mention) {
                if (isset($seenMentions[$mention->profile_id])) {
                    continue;
                }

                $seenMentions[$mention->profile_id] = true;
                $acct = AccountService::compact($mention->profile_id);
                if (empty($acct)) {
                    continue;
                }
                $uri = AccountService::getActorId($mention->profile_id);
                if ($uri) {
                    $commentObject['tag'][] = [
                        'type' => 'Mention',
                        'href' => $uri,
                        'name' => "@{$acct['username']}",
                    ];
                    $mentions[] = $uri;
                }
            }
        }

        $audience = Audience::getAudience($comment->visibility, $actor->getFollowersUrl(), $mentions);
        $commentObject['to'] = $audience['to'];
        $commentObject['cc'] = $audience['cc'];

        return [
            '@context' => 'https://www.w3.org/ns/activitystreams',
            'id' => $activityId,
            'type' => 'Update',
            'actor' => $actor->getActorId(),
            'published' => now()->toIso8601ZuluString(),
            'to' => $commentObject['to'],
            'cc' => $commentObject['cc'],
            'object' => $commentObject,
        ];
    }

    /**
     * Build an Update activity for a CommentReply
     *
     * @param  Profile  $actor  The profile updating the video
     * @param  CommentReply  $comment  The comment being updated
     * @return array The ActivityPub Update activity
     */
    public static function buildForCommentReply(Profile $actor, CommentReply $comment): array
    {
        $activityId = $comment->getObjectUrl('#updates/'.time());

        $commentObject = [
            'id' => $comment->getObjectUrl(),
            'type' => 'Note',
            'content' => $comment->caption ? AutoLinkerService::link($comment->caption) : null,
            'attributedTo' => $actor->getActorId(),
            'url' => $comment->shareUrl(),
            'summary' => null,
            'inReplyTo' => $comment->parent->getObjectUrl(),
            'sensitive' => $comment->is_sensitive,
            'published' => $comment->created_at->toIso8601ZuluString(),
            'updated' => $comment->updated_at->toIso8601ZuluString(),
            'to' => [],
            'cc' => [],
        ];
        $mentions = [];

        if ($comment->caption) {
            $commentObject['tag'] = [];

            foreach ($comment->hashtags as $hashtag) {
                $commentObject['tag'][] = [
                    'type' => 'Hashtag',
                    'name' => '#'.$hashtag->name,
                    'href' => url("/tag/{$hashtag->name}"),
                ];
            }

            $seenMentions = [];
            foreach ($comment->mentions as $mention) {
                if (isset($seenMentions[$mention->profile_id])) {
                    continue;
                }

                $seenMentions[$mention->profile_id] = true;
                $acct = AccountService::compact($mention->profile_id);
                if (empty($acct)) {
                    continue;
                }
                $uri = AccountService::getActorId($mention->profile_id);
                if ($uri) {
                    $commentObject['tag'][] = [
                        'type' => 'Mention',
                        'href' => $uri,
                        'name' => "@{$acct['username']}",
                    ];
                    $mentions[] = $uri;
                }
            }
        }

        $audience = Audience::getAudience($comment->visibility, $actor->getFollowersUrl(), $mentions);
        $commentObject['to'] = $audience['to'];
        $commentObject['cc'] = $audience['cc'];

        return [
            '@context' => 'https://www.w3.org/ns/activitystreams',
            'id' => $activityId,
            'type' => 'Update',
            'actor' => $actor->getActorId(),
            'published' => now()->toIso8601ZuluString(),
            'to' => $commentObject['to'],
            'cc' => $commentObject['cc'],
            'object' => $commentObject,
        ];
    }
}
