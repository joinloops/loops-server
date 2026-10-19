<?php

namespace App\Observers;

use App\Jobs\Federation\FetchRemoteAvatarJob;
use App\Models\Profile;
use App\Models\UsernameHistory;
use App\Services\AccountService;
use App\Services\ActivityPubCacheService;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;

class ProfileObserver implements ShouldHandleEventsAfterCommit
{
    protected $apCache;

    public function __construct(ActivityPubCacheService $apCache)
    {
        $this->apCache = $apCache;
    }

    /**
     * Handle the Profile "created" event.
     */
    public function created(Profile $profile): void
    {
        AccountService::del($profile->id);

        if ($profile->username && ! UsernameHistory::where('profile_id', $profile->id)->exists()) {
            UsernameHistory::recordInitial($profile);
        }

        if (! $profile->local && $profile->uri && $profile->avatar) {
            FetchRemoteAvatarJob::dispatch($profile)->onQueue('actor-update');
        }
    }

    /**
     * Handle the Profile "updated" event.
     */
    public function updated(Profile $profile): void
    {
        AccountService::del($profile->id);

        if (! $profile->local && $profile->uri && $profile->avatar) {
            FetchRemoteAvatarJob::dispatch($profile)->onQueue('actor-update');
        }

        $this->apCache->forget($this->apCache->profileKey($profile->id));
    }

    /**
     * Handle the Profile "deleted" event.
     */
    public function deleted(Profile $profile): void
    {
        AccountService::del($profile->id);
        $this->apCache->forget($this->apCache->profileKey($profile->id));
    }

    /**
     * Handle the Profile "restored" event.
     */
    public function restored(Profile $profile): void
    {
        AccountService::del($profile->id);
        $this->apCache->forget($this->apCache->profileKey($profile->id));
    }

    /**
     * Handle the Profile "force deleted" event.
     */
    public function forceDeleted(Profile $profile): void
    {
        AccountService::del($profile->id);
        $this->apCache->forget($this->apCache->profileKey($profile->id));
    }
}
