<?php

namespace App\Federation\ActivityBuilders;

use App\Federation\Audience;
use App\Models\Comment;
use App\Models\CommentReply;
use App\Models\Profile;
use App\Models\Video;
use App\Services\AccountService;
use App\Services\AutoLinkerService;
use App\Support\ActivityPubContext;

class CreateActivityBuilder
{
    /**
     * Build a Create activity for a Video
     *
     * @param  Profile  $actor  The local profile creating the video
     * @param  Video  $video  The video being created
     * @return array The ActivityPub Create activity
     */
    public static function buildForVideo(Profile $actor, Video $video): array
    {
        $activityId = $video->getObjectUrl('/activity');
        $videoObject = self::buildVideoObject($actor, $video);

        $videoObject = [
            '@context' => app(ActivityPubContext::class)->forVideo($video),
            'id' => $activityId,
            'type' => 'Create',
            'actor' => $actor->getActorId(),
            'published' => $video->created_at->toIso8601String(),
            'to' => $videoObject['to'],
            'cc' => $videoObject['cc'],
            'object' => $videoObject,
        ];

        if ($video->visibility === 1) {
            $videoObject['interactionPolicy'] = app(ActivityPubContext::class)->forVideoInteractionPolicy($video);
        }

        return $videoObject;
    }

    /**
     * Build a flattend Note activity for a Video
     *
     * @param  Profile  $actor  The local profile creating the video
     * @param  Video  $video  The video being created
     * @return array The ActivityPub Note activity
     */
    public static function buildForVideoFlat(Profile $actor, Video $video): array
    {
        return [
            '@context' => app(ActivityPubContext::class)->forVideo($video),
            ...self::buildVideoObject($actor, $video),
        ];
    }

    /**
     * Build a Video object for ActivityPub
     *
     * @param  Profile  $actor  The profile owning the video
     * @param  Video  $video  The video
     * @return array The ActivityPub Video object
     */
    private static function buildVideoObject(Profile $actor, Video $video): array
    {
        $videoObject = [
            'id' => $video->getObjectUrl(),
            'type' => 'Note',
            'content' => $video->caption ? AutoLinkerService::link($video->caption) : null,
            'attributedTo' => $actor->getActorId(),
            'url' => $video->shareUrl(),
            'summary' => null,
            'inReplyTo' => null,
            'sensitive' => $video->is_sensitive,
            'published' => $video->created_at->toIso8601String(),
            'to' => [],
            'cc' => [],
            'preview' => [],
            'attachment' => [
                [
                    'type' => 'Document',
                    'mediaType' => 'video/mp4',
                    'url' => $video->mediaUrl(),
                    'name' => $video->alt_text,
                    'width' => $video->thumbnail_width ?? 720,
                    'height' => $video->thumbnail_height ?? 1280,
                ],
            ],
            'likes' => [
                'id' => $video->permalink('/likes'),
                'type' => 'Collection',
                'totalItems' => (int) $video->likes,
            ],
            'shares' => [
                'id' => $video->permalink('/shares'),
                'type' => 'Collection',
                'totalItems' => (int) $video->shares,
            ],
        ];

        $mentions = [];

        if ($video->visibility === 1) {
            $videoObject['interactionPolicy'] = app(ActivityPubContext::class)->forVideoInteractionPolicy($video);
        }

        if ($video->has_thumb) {
            $thumbUrl = $video->thumb();
            $thumbMime = $video->thumbnail_mime;

            if (! $thumbMime) {
                $ext = strtolower(pathinfo($thumbUrl, PATHINFO_EXTENSION));

                $mimeTypes = [
                    'jpg' => 'image/jpeg',
                    'jpeg' => 'image/jpeg',
                    'png' => 'image/png',
                    'webp' => 'image/webp',
                ];

                $thumbMime = $mimeTypes[$ext] ?? null;
            }

            $videoObject['preview'] = [
                'type' => 'Image',
                'name' => $video->alt_text,
                'width' => $video->thumbnail_width ?? 720,
                'height' => $video->thumbnail_height ?? 1280,
                'url' => [
                    'href' => $thumbUrl,
                    'mediaType' => $thumbMime,
                ],
            ];
        } else {
            unset($videoObject['preview']);
        }

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

        return $videoObject;
    }

    /**
     * Build a Create activity for a Comment
     *
     * @param  Profile  $actor  The local profile creating the video
     * @param  Comment  $comment  The comment being created
     * @return array The ActivityPub Create activity
     */
    public static function buildForComment(Profile $actor, Comment $comment): array
    {
        $activityId = $comment->getObjectUrl('/activity');
        $commentObject = self::buildCommentObject($actor, $comment);

        $commentObj = [
            '@context' => app(ActivityPubContext::class)->forComment($comment),
            'id' => $activityId,
            'type' => 'Create',
            'actor' => $actor->getActorId(),
            'published' => $comment->created_at->toIso8601String(),
            'to' => $commentObject['to'],
            'cc' => $commentObject['cc'],
            'object' => $commentObject,
        ];

        if ($comment->visibility === 1) {
            $commentObj['interactionPolicy'] = app(ActivityPubContext::class)->forCommentInteractionPolicy($comment);
        }

        return $commentObj;
    }

    /**
     * Build a flattend Note activity for a Comment
     *
     * @param  Profile  $actor  The local profile creating the video
     * @param  Comment  $comment  The comment being created
     * @return array The ActivityPub Note activity
     */
    public static function buildForCommentFlat(Profile $actor, Comment $comment): array
    {
        return [
            '@context' => app(ActivityPubContext::class)->forComment($comment),
            ...self::buildCommentObject($actor, $comment),
        ];
    }

    /**
     * Build a Comment object for ActivityPub
     *
     * @param  Profile  $actor  The profile owning the video
     * @param  Comment  $comment  The comment
     * @return array The ActivityPub Comment object
     */
    private static function buildCommentObject(Profile $actor, Comment $comment): array
    {
        $commentObject = [
            'id' => $comment->getObjectUrl(),
            'type' => 'Note',
            'content' => $comment->caption ? AutoLinkerService::link($comment->caption) : null,
            'attributedTo' => $actor->getActorId(),
            'inReplyTo' => $comment->video->getObjectUrl(),
            'url' => $comment->shareUrl(),
            'summary' => null,
            'sensitive' => $comment->is_sensitive,
            'published' => $comment->created_at->toIso8601String(),
            'to' => [],
            'cc' => [],
        ];

        if ($comment->visibility === 1) {
            $commentObject['interactionPolicy'] = app(ActivityPubContext::class)->forCommentInteractionPolicy($comment);
        }

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

        if ($comment->has_media) {
            $media = $comment->mediaAttachments->map(function ($m) {
                return [
                    'type' => 'Document',
                    'mediaType' => $m->mime_type,
                    'url' => $m->remote_url,
                    'name' => $m->description,
                    'width' => $m->width,
                    'height' => $m->height,
                ];
            })->filter()->toArray();
            $commentObject['attachment'] = $media;
        }

        $audience = Audience::getAudience($comment->visibility, $actor->getFollowersUrl(), $mentions);
        $commentObject['to'] = $audience['to'];
        $commentObject['cc'] = $audience['cc'];

        return $commentObject;
    }

    /**
     * Build a Create activity for a CommentReply
     *
     * @param  Profile  $actor  The local profile creating the video
     * @param  CommentReply  $comment  The comment being created
     * @return array The ActivityPub Create activity
     */
    public static function buildForCommentReply(Profile $actor, CommentReply $comment): array
    {
        $activityId = $comment->getObjectUrl('/activity');
        $commentObject = self::buildCommentReplyObject($actor, $comment);

        $commentObj = [
            '@context' => app(ActivityPubContext::class)->forCommentReply($comment),
            'id' => $activityId,
            'type' => 'Create',
            'actor' => $actor->getActorId(),
            'published' => $comment->created_at->toIso8601String(),
            'to' => $commentObject['to'],
            'cc' => $commentObject['cc'],
            'object' => $commentObject,
        ];

        if ($comment->visibility === 1) {
            $commentObj['interactionPolicy'] = app(ActivityPubContext::class)->forCommentReplyInteractionPolicy($comment);
        }

        return $commentObj;
    }

    /**
     * Build a CommentReply object for ActivityPub
     *
     * @param  Profile  $actor  The profile owning the video
     * @param  CommentReply  $comment  The comment
     * @return array The ActivityPub Comment object
     */
    private static function buildCommentReplyObject(Profile $actor, CommentReply $comment): array
    {
        $commentObject = [
            'id' => $comment->getObjectUrl(),
            'type' => 'Note',
            'content' => $comment->caption ? AutoLinkerService::link($comment->caption) : null,
            'attributedTo' => $actor->getActorId(),
            'inReplyTo' => $comment->parent->getObjectUrl(),
            'url' => $comment->shareUrl(),
            'summary' => null,
            'sensitive' => $comment->is_sensitive,
            'published' => $comment->created_at->toIso8601String(),
            'to' => [],
            'cc' => [],
        ];

        if ($comment->visibility === 1) {
            $commentObject['interactionPolicy'] = app(ActivityPubContext::class)->forCommentReplyInteractionPolicy($comment);
        }

        $mentions = [];

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

        $audience = Audience::getAudience($comment->visibility, $actor->getFollowersUrl(), $mentions);
        $commentObject['to'] = $audience['to'];
        $commentObject['cc'] = $audience['cc'];

        return $commentObject;
    }

    /**
     * Build a flattend Note activity for a CommentReply
     *
     * @param  Profile  $actor  The local profile creating the video
     * @param  CommentReply  $comment  The comment being created
     * @return array The ActivityPub Note activity
     */
    public static function buildForCommentReplyFlat(Profile $actor, CommentReply $comment): array
    {
        return [
            '@context' => app(ActivityPubContext::class)->forCommentReply($comment),
            ...self::buildCommentReplyObject($actor, $comment),
        ];
    }

    /**
     * Build a Create activity for a Note (comment/reply)
     *
     * @param  Profile  $actor  The local profile creating the note
     * @param  string  $content  The note content
     * @param  string|null  $inReplyTo  Optional URL this is replying to
     * @param  array  $options  Additional options
     * @return array The ActivityPub Create activity
     */
    public static function buildForNote(Profile $actor, string $content, ?string $inReplyTo = null, array $options = []): array
    {
        $activityId = url('/ap/users/'.$actor->id.'#creates/'.uniqid());
        $noteId = $options['noteId'] ?? url('/ap/users/'.$actor->id.'/notes/'.uniqid());

        $noteObject = [
            'id' => $noteId,
            'type' => 'Note',
            'content' => $content,
            'attributedTo' => $actor->getActorId(),
            'published' => $options['published'] ?? now()->toIso8601String(),
            'to' => $options['to'] ?? ['https://www.w3.org/ns/activitystreams#Public'],
            'cc' => $options['cc'] ?? [url('/ap/users/'.$actor->id.'/followers')],
        ];

        if ($inReplyTo) {
            $noteObject['inReplyTo'] = $inReplyTo;
        }

        return [
            '@context' => 'https://www.w3.org/ns/activitystreams',
            'id' => $activityId,
            'type' => 'Create',
            'actor' => $actor->getActorId(),
            'published' => $noteObject['published'],
            'to' => $noteObject['to'],
            'cc' => $noteObject['cc'],
            'object' => $noteObject,
        ];
    }
}
