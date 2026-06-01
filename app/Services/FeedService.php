<?php

namespace App\Services;

use App\Http\Resources\VideoResource;
use App\Models\Video;
use App\Models\VideoBookmark;
use Exception;
use Illuminate\Pagination\CursorPaginator;

class FeedService
{
    public static function getAccountFeed($profileId, $limit = 10, $sort = 'Latest', $showPinned = true)
    {
        $request = request();
        $isFirstPage = $showPinned && ! $request->has('cursor');

        $query = Video::whereProfileId($profileId)
            ->whereStatus(2)
            ->when($isFirstPage, function ($query, $isFirstPage) {
                $query->where('is_pinned', false);
            });

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

        self::loadBookmarkStatus($feed->items(), auth('web')->user() ?? auth('api')->user());

        return VideoResource::collection($feed);
    }

    public static function getVideoFeed($profileId, $limit = 10, $hideAi = false)
    {
        $blockedIds = UserFilterService::getAll($profileId);

        $videos = Video::join('profiles', 'videos.profile_id', '=', 'profiles.id')
            ->when(! empty($blockedIds), fn ($q) => $q->whereNotIn('videos.profile_id', $blockedIds))
            ->when($hideAi, function ($query, $hideAi) {
                $query->where('contains_ai', false);
            })
            ->orderBy('videos.id', 'desc')
            ->where('videos.status', 2)
            ->where('is_local', true)
            ->select('videos.*')
            ->cursorPaginate($limit)
            ->withQueryString();

        self::loadBookmarkStatus($videos->items(), auth('web')->user() ?? auth('api')->user());

        return $videos;
    }

    public static function getNetworkFeedOg($profileId, $limit = 10)
    {
        $blockedIds = UserFilterService::getAll($profileId);

        $videos = Video::join('profiles', 'videos.profile_id', '=', 'profiles.id')
            ->when(! empty($blockedIds), fn ($q) => $q->whereNotIn('videos.profile_id', $blockedIds))
            ->orderBy('videos.id', 'desc')
            ->where('videos.status', 2)
            ->where('is_local', false)
            ->whereNotNull('videos.vid_optimized')
            ->select('videos.*')
            ->cursorPaginate($limit)
            ->withQueryString();

        self::loadBookmarkStatus($videos->items(), auth('web')->user() ?? auth('api')->user());

        return $videos;
    }

    public static function getNetworkFeedPager(int $profileId, int $limit = 10, ?string $decodedCursor = null): CursorPaginator
    {
        $blockedIds = UserFilterService::getAll($profileId);

        $videos = Video::query()
            ->join('profiles', 'videos.profile_id', '=', 'profiles.id')
            ->when(! empty($blockedIds), fn ($q) => $q->whereNotIn('videos.profile_id', $blockedIds))
            ->where('videos.status', 2)
            ->where('videos.is_local', false)
            ->whereNotNull('videos.vid_optimized')
            ->orderByDesc('videos.id')
            ->select('videos.*')
            ->cursorPaginate(
                perPage: $limit,
                columns: ['*'],
                cursorName: 'cursor',
                cursor: $decodedCursor
            );

        self::loadBookmarkStatus($videos->items(), auth('web')->user() ?? auth('api')->user());

        return $videos;
    }

    public static function getPublicVideoFeed($limit = 10)
    {
        $videos = Video::published()
            ->where('is_sensitive', false)
            ->where('is_local', true)
            ->where('created_at', '>', now()->subDays(93))
            ->orderBy('videos.likes', 'desc')
            ->limit($limit)
            ->get();

        self::loadBookmarkStatus($videos, auth('web')->user() ?? auth('api')->user());

        return $videos;
    }

    /**
     * Load bookmark status for a collection of videos
     */
    private static function loadBookmarkStatus($videos, $user)
    {
        $videosCollection = is_array($videos) ? collect($videos) : $videos;

        if (! $user || $videosCollection->isEmpty()) {
            return;
        }

        $profileId = $user->profile_id;
        $videoIds = $videosCollection->pluck('id')->toArray();

        $bookmarkedVideoIds = VideoBookmark::where('profile_id', $profileId)
            ->whereIn('video_id', $videoIds)
            ->pluck('video_id')
            ->flip()
            ->toArray();

        foreach ($videosCollection as $video) {
            $video->is_bookmarked = isset($bookmarkedVideoIds[$video->id]);
        }
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
