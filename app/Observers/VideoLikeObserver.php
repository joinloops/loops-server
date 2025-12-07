<?php

namespace App\Observers;

use App\Models\VideoLike;
use App\Services\NotificationService;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;

class VideoLikeObserver implements ShouldHandleEventsAfterCommit
{
    /**
     * Handle the VideoLike "created" event.
     */
    public function created(VideoLike $videoLike): void
    {
        $video = $videoLike->video;

        if ($video->is_local && (int) $video->profile_id !== (int) $videoLike->profile_id) {
            NotificationService::newVideoLike(
                $video->profile_id,
                $video->id,
                $videoLike->profile_id
            );
        }
    }

    /**
     * Handle the VideoLike "updated" event.
     */
    public function updated(VideoLike $videoLike): void
    {
        //
    }

    /**
     * Handle the VideoLike "deleted" event.
     */
    public function deleted(VideoLike $videoLike): void
    {
        $video = $videoLike->video;

        if ($video->is_local && (int) $video->profile_id !== (int) $videoLike->profile_id) {
            NotificationService::deleteVideoLike(
                $video->profile_id,
                $video->id,
                $videoLike->profile_id
            );
        }
    }

    /**
     * Handle the VideoLike "restored" event.
     */
    public function restored(VideoLike $videoLike): void
    {
        //
    }

    /**
     * Handle the VideoLike "force deleted" event.
     */
    public function forceDeleted(VideoLike $videoLike): void
    {
        $video = $videoLike->video;

        if ($video->is_local && (int) $video->profile_id !== (int) $videoLike->profile_id) {
            NotificationService::deleteVideoLike(
                $video->profile_id,
                $video->id,
                $videoLike->profile_id
            );
        }
    }
}
