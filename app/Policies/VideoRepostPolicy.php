<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Video;
use App\Models\VideoRepost;
use App\Services\UserFilterService;

class VideoRepostPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return false;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, VideoRepost $videoRepost): bool
    {
        return false;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user, Video $video): bool
    {
        if (! $user->email_verified_at || $user->can_like != 1) {
            return false;
        }

        if (
            UserFilterService::isBlocking(
                $video->profile_id,
                $user->profile_id
            )
        ) {
            return false;
        }

        return true;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, VideoRepost $videoRepost): bool
    {
        return false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, VideoRepost $videoRepost): bool
    {
        if (! $user->email_verified_at || $user->can_like != 1) {
            return false;
        }

        if ($user->is_admin) {
            return true;
        }

        return true;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, VideoRepost $videoRepost): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, VideoRepost $videoRepost): bool
    {
        if ($user->is_admin) {
            return true;
        }

        if ($user->email_verified_at && $user->can_like == 1) {
            return true;
        }

        return false;
    }
}
