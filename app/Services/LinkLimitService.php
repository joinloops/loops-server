<?php

namespace App\Services;

use App\Models\Profile;

class LinkLimitService
{
    private const BASE_LIMIT = 0;

    private const TIER_1_LIMIT = 1;

    private const TIER_2_LIMIT = 2;

    private const TIER_3_LIMIT = 3;

    private const TIER_4_LIMIT = 4;

    private const TIER_5_LIMIT = 5;

    private const TIER_1_THRESHOLD = 5;

    private const TIER_2_THRESHOLD = 100;

    private const TIER_3_THRESHOLD = 250;

    private const TIER_4_THRESHOLD = 1500;

    private const TIER_5_THRESHOLD = 3000;

    public static function baselineLimit(): int
    {
        return self::BASE_LIMIT;
    }

    public static function maxLimit(): int
    {
        return self::TIER_5_LIMIT;
    }

    public static function getMinThreshold(): int
    {
        return self::TIER_1_THRESHOLD;
    }

    public static function getMaxLinks(Profile $profile): int
    {
        $followerCount = $profile->followers ?? 0;

        if ($followerCount >= self::TIER_5_THRESHOLD) {
            return self::TIER_5_LIMIT;
        }

        if ($followerCount >= self::TIER_4_THRESHOLD) {
            return self::TIER_4_LIMIT;
        }

        if ($followerCount >= self::TIER_3_THRESHOLD) {
            return self::TIER_3_LIMIT;
        }

        if ($followerCount >= self::TIER_2_THRESHOLD) {
            return self::TIER_2_LIMIT;
        }

        if ($followerCount >= self::TIER_1_THRESHOLD) {
            return self::TIER_1_LIMIT;
        }

        return self::BASE_LIMIT;
    }

    public static function canAddLink(Profile $profile): bool
    {
        $currentCount = $profile->profileLinks()->count();
        $maxLinks = self::getMaxLinks($profile);

        return $currentCount < $maxLinks;
    }

    public static function getRemainingSlots(Profile $profile): int
    {
        $currentCount = $profile->profileLinks()->count();
        $maxLinks = self::getMaxLinks($profile);

        return max(0, $maxLinks - $currentCount);
    }
}
