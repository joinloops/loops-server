<?php

namespace App\Services;

use App\Jobs\Federation\DeliverCreateCommentActivity;
use App\Jobs\Federation\DeliverCreateCommentReplyActivity;
use App\Jobs\Federation\DeliverCreateVideoActivity;
use App\Jobs\Federation\DeliverDeleteCommentActivity;
use App\Jobs\Federation\DeliverDeleteCommentReplyActivity;
use App\Jobs\Federation\DeliverDeleteVideoActivity;
use App\Jobs\Federation\DeliverUpdateCommentActivity;
use App\Jobs\Federation\DeliverUpdateCommentReplyActivity;
use App\Jobs\Federation\DeliverUpdateVideoActivity;
use App\Models\Comment;
use App\Models\CommentReply;
use App\Models\Profile;
use App\Models\Video;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;

class FederationDispatcher
{
    protected InboxResolverService $resolver;

    public function __construct(InboxResolverService $resolver)
    {
        $this->resolver = $resolver;
    }

    public function dispatchVideoCreation(Video $video, int $chunkSize = 50): void
    {
        $actor = $video->profile;
        $allInboxes = collect();

        $mentions = $video->mentions;
        if ($mentions->isNotEmpty()) {
            $mentionInboxes = $this->resolver->getMentionInboxes($mentions);

            foreach ($mentionInboxes as $inbox) {
                $inboxUrl = $inbox['inbox'];
                if ($allInboxes->has($inboxUrl)) {
                    $allInboxes[$inboxUrl]['profile_ids'] = array_unique(array_merge(
                        $allInboxes[$inboxUrl]['profile_ids'],
                        $inbox['profile_ids']
                    ));
                } else {
                    $allInboxes[$inboxUrl] = $inbox;
                }
            }
        }

        $this->resolver->chunkFollowerInboxes(
            $actor->id,
            function ($inboxes) use (&$allInboxes) {
                foreach ($inboxes as $inbox) {
                    $inboxUrl = $inbox['inbox'];
                    if ($allInboxes->has($inboxUrl)) {
                        $allInboxes[$inboxUrl]['profile_ids'] = array_unique(array_merge(
                            $allInboxes[$inboxUrl]['profile_ids'],
                            $inbox['profile_ids']
                        ));
                    } else {
                        $allInboxes[$inboxUrl] = $inbox;
                    }
                }
            },
            $chunkSize
        );

        if ($allInboxes->isEmpty()) {
            if (config('logging.dev_log')) {
                Log::info('No remote recipients to deliver to', [
                    'profile_id' => $actor->id,
                    'video_id' => $video->id,
                ]);
            }
        } else {
            $jobs = $allInboxes->map(function ($inbox) use ($video) {
                return new DeliverCreateVideoActivity(
                    $video,
                    $inbox['inbox'],
                    $inbox['profile_ids']
                );
            })->toArray();

            Bus::batch($jobs)
                ->name("Deliver Video {$video->id}")
                ->allowFailures()
                ->onQueue('activitypub-out')
                ->dispatch();

            if (config('logging.dev_log')) {
                Log::info('Video creation dispatched', [
                    'profile_id' => $actor->id,
                    'video_id' => $video->id,
                    'inbox_count' => count($jobs),
                ]);
            }
        }

        if ($actor->local && $video->visibility == 1) {
            app(\App\Services\RelayService::class)->deliverToRelays($actor, \App\Federation\ActivityBuilders\CreateActivityBuilder::buildForVideo($actor, $video));
        }
    }

    public function dispatchVideoUpdate(Video $video, int $chunkSize = 50): void
    {
        $actor = $video->profile;
        $allInboxes = collect();

        $mentions = $video->mentions;
        if ($mentions->isNotEmpty()) {
            $mentionInboxes = $this->resolver->getMentionInboxes($mentions);

            foreach ($mentionInboxes as $inbox) {
                $inboxUrl = $inbox['inbox'];
                if ($allInboxes->has($inboxUrl)) {
                    $allInboxes[$inboxUrl]['profile_ids'] = array_unique(array_merge(
                        $allInboxes[$inboxUrl]['profile_ids'],
                        $inbox['profile_ids']
                    ));
                } else {
                    $allInboxes[$inboxUrl] = $inbox;
                }
            }
        }

        $this->resolver->chunkFollowerInboxes(
            $actor->id,
            function ($inboxes) use (&$allInboxes) {
                foreach ($inboxes as $inbox) {
                    $inboxUrl = $inbox['inbox'];
                    if ($allInboxes->has($inboxUrl)) {
                        $allInboxes[$inboxUrl]['profile_ids'] = array_unique(array_merge(
                            $allInboxes[$inboxUrl]['profile_ids'],
                            $inbox['profile_ids']
                        ));
                    } else {
                        $allInboxes[$inboxUrl] = $inbox;
                    }
                }
            },
            $chunkSize
        );

        if ($allInboxes->isEmpty()) {
            if (config('logging.dev_log')) {
                Log::info('No remote recipients to deliver to', [
                    'profile_id' => $actor->id,
                    'video_id' => $video->id,
                ]);
            }

            return;
        }

        $jobs = $allInboxes->map(function ($inbox) use ($video) {
            return new DeliverUpdateVideoActivity(
                $video,
                $inbox['inbox'],
                $inbox['profile_ids']
            );
        })->toArray();

        Bus::batch($jobs)
            ->name("Deliver Video Update {$video->id}")
            ->allowFailures()
            ->onQueue('activitypub-out')
            ->dispatch();

        if (config('logging.dev_log')) {
            Log::info('Video update dispatched', [
                'video_id' => $video->id,
                'inbox_count' => count($jobs),
                'total_recipients' => collect($jobs)->sum(fn ($job) => count($job->recipientProfileIds)),
            ]);
        }
    }

    public function dispatchVideoDeleteToAllKnownInboxes(Profile $actor, $videoId, $objectUrl, int $chunkSize = 50): void
    {
        $allInboxes = collect();

        $this->resolver->chunkAllKnownInboxesFlat(
            function ($inboxes) use (&$allInboxes) {
                $allInboxes = $allInboxes->merge($inboxes);
            },
            $chunkSize
        );

        if ($allInboxes->isEmpty()) {
            Log::info('No remote inboxes to deliver delete to', [
                'resource_type' => Video::class,
                'resource_id' => $videoId,
            ]);

            return;
        }

        $jobs = $allInboxes->map(function ($inboxUrl) use ($actor, $objectUrl) {
            return new DeliverDeleteVideoActivity(
                $actor,
                $inboxUrl,
                $objectUrl
            );
        })->toArray();

        Bus::batch($jobs)
            ->name("Delete Video {$videoId}")
            ->allowFailures()
            ->onQueue('activitypub-out')
            ->dispatch();

        if (config('logging.dev_log')) {
            Log::info('Delete Video activity dispatched to all known inboxes', [
                'resource_type' => Video::class,
                'resource_id' => $videoId,
                'inbox_count' => count($jobs),
            ]);
        }
    }

    public function dispatchCommentCreation(Comment $comment, int $chunkSize = 50): void
    {
        $actor = $comment->profile;
        $allInboxes = collect();

        $mentions = $comment->mentions;
        if ($mentions->isNotEmpty()) {
            $mentionInboxes = $this->resolver->getMentionInboxes($mentions);

            foreach ($mentionInboxes as $inbox) {
                $inboxUrl = $inbox['inbox'];
                if ($allInboxes->has($inboxUrl)) {
                    $allInboxes[$inboxUrl]['profile_ids'] = array_unique(array_merge(
                        $allInboxes[$inboxUrl]['profile_ids'],
                        $inbox['profile_ids']
                    ));
                } else {
                    $allInboxes[$inboxUrl] = $inbox;
                }
            }
        }

        $this->resolver->chunkFollowerInboxes(
            $actor->id,
            function ($inboxes) use (&$allInboxes) {
                foreach ($inboxes as $inbox) {
                    $inboxUrl = $inbox['inbox'];
                    if ($allInboxes->has($inboxUrl)) {
                        $allInboxes[$inboxUrl]['profile_ids'] = array_unique(array_merge(
                            $allInboxes[$inboxUrl]['profile_ids'],
                            $inbox['profile_ids']
                        ));
                    } else {
                        $allInboxes[$inboxUrl] = $inbox;
                    }
                }
            },
            $chunkSize
        );

        if ($allInboxes->isEmpty()) {
            if (config('logging.dev_log')) {
                Log::info('No remote recipients to deliver to', [
                    'profile_id' => $actor->id,
                    'comment_id' => $comment->id,
                ]);
            }

            return;
        }

        $jobs = $allInboxes->map(function ($inbox) use ($comment) {
            return new DeliverCreateCommentActivity(
                $comment,
                $inbox['inbox'],
                $inbox['profile_ids']
            );
        })->toArray();

        Bus::batch($jobs)
            ->name("Deliver Comment {$comment->id}")
            ->allowFailures()
            ->onQueue('activitypub-out')
            ->dispatch();

        if (config('logging.dev_log')) {
            Log::info('Comment creation dispatched', [
                'comment_id' => $comment->id,
                'inbox_count' => count($jobs),
                'total_recipients' => collect($jobs)->sum(fn ($job) => count($job->recipientProfileIds)),
            ]);
        }
    }

    public function dispatchCommentUpdate(Comment $comment, int $chunkSize = 50): void
    {
        $actor = $comment->profile;
        $allInboxes = collect();

        $mentions = $comment->mentions;
        if ($mentions->isNotEmpty()) {
            $mentionInboxes = $this->resolver->getMentionInboxes($mentions);

            foreach ($mentionInboxes as $inbox) {
                $inboxUrl = $inbox['inbox'];
                if ($allInboxes->has($inboxUrl)) {
                    $allInboxes[$inboxUrl]['profile_ids'] = array_unique(array_merge(
                        $allInboxes[$inboxUrl]['profile_ids'],
                        $inbox['profile_ids']
                    ));
                } else {
                    $allInboxes[$inboxUrl] = $inbox;
                }
            }
        }

        $this->resolver->chunkFollowerInboxes(
            $actor->id,
            function ($inboxes) use (&$allInboxes) {
                foreach ($inboxes as $inbox) {
                    $inboxUrl = $inbox['inbox'];
                    if ($allInboxes->has($inboxUrl)) {
                        $allInboxes[$inboxUrl]['profile_ids'] = array_unique(array_merge(
                            $allInboxes[$inboxUrl]['profile_ids'],
                            $inbox['profile_ids']
                        ));
                    } else {
                        $allInboxes[$inboxUrl] = $inbox;
                    }
                }
            },
            $chunkSize
        );

        if ($allInboxes->isEmpty()) {
            if (config('logging.dev_log')) {
                Log::info('No remote recipients to deliver to', [
                    'profile_id' => $actor->id,
                    'comment_id' => $comment->id,
                ]);
            }

            return;
        }

        $jobs = $allInboxes->map(function ($inbox) use ($comment) {
            return new DeliverUpdateCommentActivity(
                $comment,
                $inbox['inbox'],
                $inbox['profile_ids']
            );
        })->toArray();

        Bus::batch($jobs)
            ->name("Deliver Comment Update {$comment->id}")
            ->allowFailures()
            ->onQueue('activitypub-out')
            ->dispatch();

        if (config('logging.dev_log')) {
            Log::info('Comment creation dispatched', [
                'comment_id' => $comment->id,
                'inbox_count' => count($jobs),
                'total_recipients' => collect($jobs)->sum(fn ($job) => count($job->recipientProfileIds)),
            ]);
        }
    }

    public function dispatchCommentDeleteToAllKnownInboxes(Profile $actor, $commentId, $objectUrl, int $chunkSize = 50): void
    {
        $allInboxes = collect();

        $this->resolver->chunkAllKnownInboxesFlat(
            function ($inboxes) use (&$allInboxes) {
                $allInboxes = $allInboxes->merge($inboxes);
            },
            $chunkSize
        );

        if ($allInboxes->isEmpty()) {
            Log::info('No remote inboxes to deliver delete to', [
                'resource_type' => Comment::class,
                'resource_id' => $commentId,
            ]);

            return;
        }

        $jobs = $allInboxes->map(function ($inboxUrl) use ($actor, $objectUrl) {
            return new DeliverDeleteCommentActivity(
                $actor,
                $inboxUrl,
                $objectUrl
            );
        })->toArray();

        Bus::batch($jobs)
            ->name("Delete Comment {$commentId}")
            ->allowFailures()
            ->onQueue('activitypub-out')
            ->dispatch();

        if (config('logging.dev_log')) {
            Log::info('Delete Comment activity dispatched to all known inboxes', [
                'resource_type' => Comment::class,
                'resource_id' => $commentId,
                'inbox_count' => count($jobs),
            ]);
        }
    }

    public function dispatchCommentReplyCreation(CommentReply $comment, int $chunkSize = 50): void
    {
        $actor = $comment->profile;
        $allInboxes = collect();

        $mentions = $comment->mentions;
        if ($mentions->isNotEmpty()) {
            $mentionInboxes = $this->resolver->getMentionInboxes($mentions);

            foreach ($mentionInboxes as $inbox) {
                $inboxUrl = $inbox['inbox'];
                if ($allInboxes->has($inboxUrl)) {
                    $allInboxes[$inboxUrl]['profile_ids'] = array_unique(array_merge(
                        $allInboxes[$inboxUrl]['profile_ids'],
                        $inbox['profile_ids']
                    ));
                } else {
                    $allInboxes[$inboxUrl] = $inbox;
                }
            }
        }

        $this->resolver->chunkFollowerInboxes(
            $actor->id,
            function ($inboxes) use (&$allInboxes) {
                foreach ($inboxes as $inbox) {
                    $inboxUrl = $inbox['inbox'];
                    if ($allInboxes->has($inboxUrl)) {
                        $allInboxes[$inboxUrl]['profile_ids'] = array_unique(array_merge(
                            $allInboxes[$inboxUrl]['profile_ids'],
                            $inbox['profile_ids']
                        ));
                    } else {
                        $allInboxes[$inboxUrl] = $inbox;
                    }
                }
            },
            $chunkSize
        );

        if ($allInboxes->isEmpty()) {
            if (config('logging.dev_log')) {
                Log::info('No remote recipients to deliver to', [
                    'profile_id' => $actor->id,
                    'comment_id' => $comment->id,
                ]);
            }

            return;
        }

        $jobs = $allInboxes->map(function ($inbox) use ($comment) {
            return new DeliverCreateCommentReplyActivity(
                $comment,
                $inbox['inbox'],
                $inbox['profile_ids']
            );
        })->toArray();

        Bus::batch($jobs)
            ->name("Deliver Comment Reply {$comment->id}")
            ->allowFailures()
            ->onQueue('activitypub-out')
            ->dispatch();

        if (config('logging.dev_log')) {
            Log::info('Comment reply creation dispatched', [
                'comment_id' => $comment->id,
                'inbox_count' => count($jobs),
                'total_recipients' => collect($jobs)->sum(fn ($job) => count($job->recipientProfileIds)),
            ]);
        }
    }

    public function dispatchCommentReplyUpdate(CommentReply $comment, int $chunkSize = 50): void
    {
        $actor = $comment->profile;
        $allInboxes = collect();

        $mentions = $comment->mentions;
        if ($mentions->isNotEmpty()) {
            $mentionInboxes = $this->resolver->getMentionInboxes($mentions);

            foreach ($mentionInboxes as $inbox) {
                $inboxUrl = $inbox['inbox'];
                if ($allInboxes->has($inboxUrl)) {
                    $allInboxes[$inboxUrl]['profile_ids'] = array_unique(array_merge(
                        $allInboxes[$inboxUrl]['profile_ids'],
                        $inbox['profile_ids']
                    ));
                } else {
                    $allInboxes[$inboxUrl] = $inbox;
                }
            }
        }

        $this->resolver->chunkFollowerInboxes(
            $actor->id,
            function ($inboxes) use (&$allInboxes) {
                foreach ($inboxes as $inbox) {
                    $inboxUrl = $inbox['inbox'];
                    if ($allInboxes->has($inboxUrl)) {
                        $allInboxes[$inboxUrl]['profile_ids'] = array_unique(array_merge(
                            $allInboxes[$inboxUrl]['profile_ids'],
                            $inbox['profile_ids']
                        ));
                    } else {
                        $allInboxes[$inboxUrl] = $inbox;
                    }
                }
            },
            $chunkSize
        );

        if ($allInboxes->isEmpty()) {
            if (config('logging.dev_log')) {
                Log::info('No remote recipients to deliver to', [
                    'profile_id' => $actor->id,
                    'comment_id' => $comment->id,
                ]);
            }

            return;
        }

        $jobs = $allInboxes->map(function ($inbox) use ($comment) {
            return new DeliverUpdateCommentReplyActivity(
                $comment,
                $inbox['inbox'],
                $inbox['profile_ids']
            );
        })->toArray();

        Bus::batch($jobs)
            ->name("Deliver Comment Reply Update {$comment->id}")
            ->allowFailures()
            ->onQueue('activitypub-out')
            ->dispatch();

        if (config('logging.dev_log')) {
            Log::info('Comment reply update dispatched', [
                'comment_id' => $comment->id,
                'inbox_count' => count($jobs),
                'total_recipients' => collect($jobs)->sum(fn ($job) => count($job->recipientProfileIds)),
            ]);
        }
    }

    public function dispatchCommentReplyDeleteToAllKnownInboxes(Profile $actor, $commentId, $objectUrl, int $chunkSize = 50): void
    {
        $allInboxes = collect();

        $this->resolver->chunkAllKnownInboxesFlat(
            function ($inboxes) use (&$allInboxes) {
                $allInboxes = $allInboxes->merge($inboxes);
            },
            $chunkSize
        );

        if ($allInboxes->isEmpty()) {
            Log::info('No remote inboxes to deliver delete to', [
                'resource_type' => CommentReply::class,
                'resource_id' => $commentId,
            ]);

            return;
        }

        $jobs = $allInboxes->map(function ($inboxUrl) use ($actor, $objectUrl) {
            return new DeliverDeleteCommentReplyActivity(
                $actor,
                $inboxUrl,
                $objectUrl
            );
        })->toArray();

        Bus::batch($jobs)
            ->name("Delete Comment Reply {$commentId}")
            ->allowFailures()
            ->onQueue('activitypub-out')
            ->dispatch();

        if (config('logging.dev_log')) {
            Log::info('Delete Comment Reply activity dispatched to all known inboxes', [
                'resource_type' => CommentReply::class,
                'resource_id' => $commentId,
                'inbox_count' => count($jobs),
            ]);
        }
    }
}
