<?php

namespace App\Services;

use App\Models\Follower;
use App\Models\Profile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class FollowerService
{
    public const FOLLOWS_KEY = 'api:s:follower:';

    public static function follows($aid, $pid)
    {
        return Cache::remember(self::FOLLOWS_KEY.$aid.':'.$pid, now()->addHours(6), function () use ($aid, $pid) {
            return Follower::whereProfileId($aid)->whereFollowingId($pid)->exists();
        });
    }

    public static function del($aid, $pid)
    {
        Cache::forget(self::FOLLOWS_KEY.$aid.':'.$pid);
        Cache::forget(self::FOLLOWS_KEY.$pid.':'.$aid);
    }

    public static function sync($pid)
    {
        DB::beginTransaction();

        try {
            $profile = Profile::where('id', $pid)->lockForUpdate()->first();

            if (! $profile) {
                throw new \Exception("Profile with ID {$pid} not found");
            }

            // Only count followers/following where the related profile is active (status=1)
            $followers = Follower::whereFollowingId($pid)
                ->join('profiles', 'followers.profile_id', '=', 'profiles.id')
                ->where('profiles.status', 1)
                ->count();

            $following = Follower::whereProfileId($pid)
                ->join('profiles', 'followers.following_id', '=', 'profiles.id')
                ->where('profiles.status', 1)
                ->count();

            $affected = Profile::where('id', $pid)->update([
                'followers' => $followers,
                'following' => $following,
            ]);

            if ($affected === 0) {
                DB::rollback();

                return;
            }

            DB::commit();

            if (config('logging.dev_log')) {
                Log::info("Recalculated counts for profile {$pid}: followers={$followers}, following={$following}");
            }

            AccountService::del($pid);

        } catch (\Exception $e) {
            DB::rollback();
            if (config('logging.dev_log')) {
                Log::error("Failed to recalculate counts for profile {$pid}: ".$e->getMessage());
            }
            throw $e;
        }
    }

    public static function refreshAndSync($aid, $pid)
    {
        self::del($aid, $pid);
        self::sync($aid);
        self::sync($pid);

        return 1;
    }
}
