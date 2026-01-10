<?php

namespace App\Policies;

use App\Models\Profile;
use App\Models\User;
use App\Services\UserFilterService;

class ProfilePolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        if ($user->is_admin) {
            return true;
        }

        if (! $user->email_verified_at) {
            return false;
        }

        return true;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Profile $profile): bool
    {
        if ($user->is_admin) {
            return true;
        }

        // Confirm profile is active (1)
        if ($profile->status != 1) {
            return false;
        }

        if (! $user->email_verified_at) {
            return false;
        }

        if ($user->profile_id == $profile->id) {
            return true;
        }

        if (
            UserFilterService::isBlocking(
                $profile->id,
                $user->profile_id
            )
        ) {
            return false;
        }

        return true;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return false;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Profile $profile): bool
    {
        return false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Profile $profile): bool
    {
        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Profile $profile): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Profile $profile): bool
    {
        return false;
    }
}
