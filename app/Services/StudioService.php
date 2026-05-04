<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class StudioService
{
    const CACHE_KEY = 'loops:services:studio:';

    const CACHE_TTL_HOURS = 12;

    public function getSummary($profileId = false)
    {
        if (! $profileId) {
            return [];
        }

        return Cache::remember(
            self::CACHE_KEY."analytics:summary:pid:{$profileId}",
            now()->addHours(self::CACHE_TTL_HOURS),
            fn () => $this->buildSummary($profileId)
        );
    }

    public function forgetSummary($profileId = false)
    {
        if (! $profileId) {
            return;
        }

        Cache::forget(self::CACHE_KEY."analytics:summary:pid:{$profileId}");
    }

    private function buildSummary(int $profileId): array
    {
        $now = Carbon::now();
        $range = 7;
        $currentSince = $now->copy()->subDays($range)->startOfDay();
        $previousSince = $now->copy()->subDays($range * 2)->startOfDay();
        $previousUntil = $currentSince;

        $currentViews = DB::table('feed_impressions')
            ->join('videos', 'videos.id', '=', 'feed_impressions.video_id')
            ->where('videos.profile_id', $profileId)
            ->where('feed_impressions.viewed_at', '>=', $currentSince)
            ->count();

        $previousViews = DB::table('feed_impressions')
            ->join('videos', 'videos.id', '=', 'feed_impressions.video_id')
            ->where('videos.profile_id', $profileId)
            ->whereBetween('feed_impressions.viewed_at', [$previousSince, $previousUntil])
            ->count();

        $currentFollowers = DB::table('followers')
            ->where('following_id', $profileId)
            ->where('created_at', '>=', $currentSince)
            ->count();

        $previousFollowers = DB::table('followers')
            ->where('following_id', $profileId)
            ->whereBetween('created_at', [$previousSince, $previousUntil])
            ->count();

        $currentLikes = DB::table('video_likes')
            ->join('videos', 'videos.id', '=', 'video_likes.video_id')
            ->where('videos.profile_id', $profileId)
            ->where('video_likes.created_at', '>=', $currentSince)
            ->count();

        $previousLikes = DB::table('video_likes')
            ->join('videos', 'videos.id', '=', 'video_likes.video_id')
            ->where('videos.profile_id', $profileId)
            ->whereBetween('video_likes.created_at', [$previousSince, $previousUntil])
            ->count();

        $latest = DB::table('videos')
            ->where('status', 2)
            ->where('profile_id', $profileId)
            ->orderByDesc('created_at')
            ->select('id')
            ->first();

        $latestPost = null;
        if ($latest) {
            $likes = DB::table('video_likes')
                ->where('video_id', $latest->id)
                ->count();

            $comments = DB::table('comments')
                ->where('video_id', $latest->id)
                ->count()
                + DB::table('comment_replies')
                    ->where('video_id', $latest->id)
                    ->count();

            $latestPost = VideoService::getCompactStats($latest->id, true);
        }

        return [
            'range' => $range,
            'views' => [
                'total' => $currentViews,
                'change_pct' => $this->pctChange($previousViews, $currentViews),
            ],
            'followers' => [
                'total' => $currentFollowers,
                'change_pct' => $this->pctChange($previousFollowers, $currentFollowers),
            ],
            'likes' => [
                'total' => $currentLikes,
                'change_pct' => $this->pctChange($previousLikes, $currentLikes),
            ],
            'latest_post' => $latestPost,
        ];
    }

    private function pctChange(int $previous, int $current): float
    {
        if ($previous === 0) {
            return $current > 0 ? 100.0 : 0.0;
        }

        return round((($current - $previous) / $previous) * 100, 1);
    }
}
