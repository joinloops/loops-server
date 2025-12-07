<?php

namespace App\Services;

use App\Http\Resources\VideoResource;
use App\Models\Video;
use Exception;

class FeedService
{
    public static function getAccountFeed($profileId, $limit = 10, $sort = 'Latest')
    {
        $request = request();
        $isFirstPage = ! $request->has('cursor');

        $query = Video::whereProfileId($profileId)
            ->whereStatus(2)
            ->where('is_pinned', false);

        match ($sort) {
            'Latest' => $query->orderByDesc('id'),
            'Popular' => $query->orderByDesc('likes'),
            'Oldest' => $query->orderBy('created_at'),
            default => $query->orderByDesc('id')
        };

        $feed = $query->cursorPaginate($limit)->withQueryString();

        if ($isFirstPage) {
            $pinnedVideos = Video::whereProfileId($profileId)
                ->whereStatus(2)
                ->where('is_pinned', true)
                ->orderBy('pinned_order')
                ->limit(3)
                ->get();

            $items = $pinnedVideos->merge($feed->items());
            $feed->setCollection($items);
        }

        return VideoResource::collection($feed);
    }

    public static function getVideoFeed($profileId, $limit = 10)
    {
        $blockedSubquery = UserFilterService::getFilters($profileId);

        $videos = Video::join('profiles', 'videos.profile_id', '=', 'profiles.id')
            ->leftJoinSub($blockedSubquery, 'blocked', function ($join) {
                $join->on('videos.profile_id', '=', 'blocked.account_id');
            })
            ->whereNull('blocked.account_id')
            ->orderBy('videos.id', 'desc')
            ->where('videos.status', 2)
            ->where('is_local', true)
            ->whereNotNull('videos.vid_optimized')
            ->select('videos.*')
            ->cursorPaginate($limit)
            ->withQueryString();

        return $videos;
    }

    public static function getPublicVideoFeed($limit = 10)
    {
        $videos = Video::published()
            ->where('created_at', '>', now()->subDays(93))
            ->orderBy('videos.likes', 'desc')
            ->limit($limit)
            ->get();

        return $videos;
    }

    public static function emptyCursor($request)
    {
        return [
            'data' => [],
            'links' => [
                'first' => null,
                'last' => null,
                'prev' => null,
                'next' => null,
            ],
            'meta' => [
                'path' => $request->url(),
                'per_page' => 2,
                'next_cursor' => null,
                'prev_cursor' => null,
            ],
        ];
    }

    public static function enforcePaginationLimit($request)
    {
        if (config('loops.feed.fyp.max_page.enabled') && $request->filled('cursor')) {
            $preCursor = base64_decode($request->input('cursor'));
            if (! $preCursor) {
                return self::emptyCursor($request);
            }
            try {
                $cursorId = json_decode($preCursor, true);
                if (! $cursorId || ! isset($cursorId['videos.id'])) {
                    throw new Exception('Invalid parameters');
                }
                $cursor = $cursorId['videos.id'];
            } catch (Exception $e) {
                return self::emptyCursor($request);
            }
            $maxId = SnowflakeService::byDate(now()->subDays(config('loops.feed.fyp.max_page.max_days')));
            if ($cursor < $maxId) {
                return self::emptyCursor($request);
            }
        }
    }

    public static function enforceFollowingPaginationLimit($request)
    {
        if (config('loops.feed.following.max_page.enabled') && $request->filled('cursor')) {
            $preCursor = base64_decode($request->input('cursor'));
            if (! $preCursor) {
                return self::emptyCursor($request);
            }
            try {
                $cursorId = json_decode($preCursor, true);
                if (! $cursorId || ! isset($cursorId['videos.id'])) {
                    throw new Exception('Invalid parameters');
                }
                $cursor = $cursorId['videos.id'];
            } catch (Exception $e) {
                return self::emptyCursor($request);
            }
            $maxId = SnowflakeService::byDate(now()->subDays(config('loops.feed.following.max_page.max_days')));
            if ($cursor < $maxId) {
                return self::emptyCursor($request);
            }
        }
    }
}
