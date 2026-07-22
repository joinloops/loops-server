<?php

namespace App\Services;

use App\Models\Profile;

class DmPermissionService
{
    public static function canInitiate(Profile $sender, Profile $recipient): DmPermissionResult
    {
        if (! $sender->can_dm) {
            return DmPermissionResult::deny('Direct messages are not available for your account.');
        }

        if (! $recipient->can_dm) {
            return DmPermissionResult::deny('Direct messages are not available for that account.');
        }

        if (self::hasBlock($sender->id, $recipient->id)) {
            return DmPermissionResult::deny('Unblock this account to message them.', 422);
        }

        $minAccountAgeDays = (int) config('loops.dm.compose.min_account_age_days');
        $minFollowers = (int) config('loops.dm.compose.min_followers');

        $restricted = $sender->created_at->gt(now()->subDays($minAccountAgeDays)) || (int) $sender->followers < $minFollowers;
        if ($restricted) {
            $exempt = self::isMutual($sender->id, $recipient->id);
            if (! $exempt) {
                return DmPermissionResult::deny('You do not have permission for this action');
            }
        }

        if ($sender->dm_privacy === 'following' && ! self::isMutual($sender->id, $recipient->id)) {
            return DmPermissionResult::deny(
                'Your account can only message accounts that follow you back right now.'
            );
        }

        return DmPermissionResult::allow();
    }

    protected static function hasBlock(int $profileId, int $targetId): bool
    {
        return UserFilterService::isBlocking($profileId, $targetId);
    }

    protected static function isMutual(int $a, int $b): bool
    {
        $me = FollowerService::follows($a, $b);
        $them = FollowerService::follows($b, $a);

        return $me && $them;
    }
}
