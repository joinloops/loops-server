<?php

namespace App\Observers;

use App\Models\Profile;
use App\Models\Video;
use App\Services\AccountService;
use App\Services\AtomFeedService;
use App\Services\StudioService;

class VideoObserver
{
    /**
     * Handle the Video "created" event.
     */
    public function created(Video $video): void
    {
        $this->updateProfileVideoCount($video->profile_id);
        AtomFeedService::invalidate($video->profile_id);
    }

    /**
     * Handle the Video "updated" event.
     */
    public function updated(Video $video): void
    {
        $this->updateProfileVideoCount($video->profile_id);
        AtomFeedService::invalidate($video->profile_id);
    }

    /**
     * Handle the Video "deleted" event.
     */
    public function deleted(Video $video): void
    {
        $this->updateProfileVideoCount($video->profile_id);
        AtomFeedService::invalidate($video->profile_id);
    }

    /**
     * Handle the Video "restored" event.
     */
    public function restored(Video $video): void
    {
        $this->updateProfileVideoCount($video->profile_id);
        AtomFeedService::invalidate($video->profile_id);
    }

    /**
     * Handle the Video "force deleted" event.
     */
    public function forceDeleted(Video $video): void
    {
        $this->updateProfileVideoCount($video->profile_id);
        AtomFeedService::invalidate($video->profile_id);
    }

    /**
     * Update the video count for a profile based on actual Video table count
     */
    protected function updateProfileVideoCount($profileId): void
    {
        if (! $profileId) {
            return;
        }
        $count = Video::published()->where('profile_id', $profileId)->count();
        Profile::where('id', $profileId)->update(['video_count' => $count]);
        AccountService::del($profileId);
        app(StudioService::class)->forgetSummary($profileId);
    }
}
