<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ProfileLink;
use Carbon\Carbon;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class StudioAnalyticsController extends Controller
{
    private const VALID_RANGES = [7, 30, 60];

    private const MAX_RANGE = 60;

    private const CACHE_TTL_HOURS = 12;

    public function videoViews(Request $request)
    {
        $profileId = $request->user()->profile_id;
        $range = $this->resolveRange($request);

        $payload = $this->buildSeries(
            cacheKey: 'studio:analytics:views:pid:'.$profileId,
            range: $range,
            valueKey: 'views',
            queryFn: function (Carbon $since) use ($profileId) {
                return DB::table('feed_impressions')
                    ->join('videos', 'videos.id', '=', 'feed_impressions.video_id')
                    ->where('videos.profile_id', $profileId)
                    ->where('feed_impressions.viewed_at', '>=', $since)
                    ->select(
                        DB::raw('DATE(feed_impressions.viewed_at) as date'),
                        DB::raw('COUNT(feed_impressions.profile_id) as views')
                    )
                    ->groupBy('date')
                    ->orderBy('date')
                    ->get()
                    ->keyBy('date');
            }
        );

        return response()->json($payload);
    }

    public function newFollowers(Request $request)
    {
        $profileId = $request->user()->profile_id;
        $range = $this->resolveRange($request);

        $payload = $this->buildSeries(
            cacheKey: 'studio:analytics:followers:pid:'.$profileId,
            range: $range,
            valueKey: 'count',
            queryFn: function (Carbon $since) use ($profileId) {
                return DB::table('followers')
                    ->where('following_id', $profileId)
                    ->where('created_at', '>=', $since)
                    ->select(
                        DB::raw('DATE(created_at) as date'),
                        DB::raw('COUNT(profile_id) as count')
                    )
                    ->groupBy('date')
                    ->orderBy('date')
                    ->get()
                    ->keyBy('date');
            }
        );

        return response()->json($payload);
    }

    public function comments(Request $request)
    {
        $profileId = $request->user()->profile_id;
        $range = $this->resolveRange($request);

        $payload = $this->buildSeries(
            cacheKey: 'studio:analytics:comments:pid:'.$profileId,
            range: $range,
            valueKey: 'count',
            queryFn: function (Carbon $since) use ($profileId) {
                $replies = DB::table('comment_replies')
                    ->join('videos', 'videos.id', '=', 'comment_replies.video_id')
                    ->where('videos.profile_id', $profileId)
                    ->where('comment_replies.created_at', '>=', $since)
                    ->select(
                        DB::raw('DATE(comment_replies.created_at) as date'),
                        DB::raw('COUNT(*) as count')
                    )
                    ->groupBy('date');

                $unioned = DB::table('comments')
                    ->join('videos', 'videos.id', '=', 'comments.video_id')
                    ->where('videos.profile_id', $profileId)
                    ->where('comments.created_at', '>=', $since)
                    ->select(
                        DB::raw('DATE(comments.created_at) as date'),
                        DB::raw('COUNT(*) as count')
                    )
                    ->groupBy('date')
                    ->unionAll($replies);

                return DB::query()
                    ->fromSub($unioned, 'combined')
                    ->select('date', DB::raw('SUM(count) as count'))
                    ->groupBy('date')
                    ->orderBy('date')
                    ->get()
                    ->keyBy('date');
            }
        );

        return response()->json($payload);
    }

    public function likes(Request $request)
    {
        $profileId = $request->user()->profile_id;
        $range = $this->resolveRange($request);

        $payload = $this->buildSeries(
            cacheKey: 'studio:analytics:likes:pid:'.$profileId,
            range: $range,
            valueKey: 'count',
            queryFn: function (Carbon $since) use ($profileId) {
                return DB::table('video_likes')
                    ->join('videos', 'videos.id', '=', 'video_likes.video_id')
                    ->where('videos.profile_id', $profileId)
                    ->where('video_likes.created_at', '>=', $since)
                    ->select(
                        DB::raw('DATE(video_likes.created_at) as date'),
                        DB::raw('COUNT(*) as count')
                    )
                    ->groupBy('date')
                    ->orderBy('date')
                    ->get()
                    ->keyBy('date');
            }
        );

        return response()->json($payload);
    }

    public function shares(Request $request)
    {
        $profileId = $request->user()->profile_id;
        $range = $this->resolveRange($request);

        $payload = $this->buildSeries(
            cacheKey: 'studio:analytics:shares:pid:'.$profileId,
            range: $range,
            valueKey: 'count',
            queryFn: function (Carbon $since) use ($profileId) {
                return DB::table('video_reposts')
                    ->join('videos', 'videos.id', '=', 'video_reposts.video_id')
                    ->where('videos.profile_id', $profileId)
                    ->where('video_reposts.created_at', '>=', $since)
                    ->select(
                        DB::raw('DATE(video_reposts.created_at) as date'),
                        DB::raw('COUNT(*) as count')
                    )
                    ->groupBy('date')
                    ->orderBy('date')
                    ->get()
                    ->keyBy('date');
            }
        );

        return response()->json($payload);
    }

    public function profileLinks(Request $request)
    {
        $profileId = $request->user()->profile_id;

        $res = ProfileLink::whereProfileId($profileId)
            ->get()
            ->map(fn ($link) => ['url' => $link->url, 'clicks' => $link->click_count]);

        return response()->json([
            'data' => $res,
        ]);
    }

    private function resolveRange(Request $request): int
    {
        $request->validate([
            'range' => ['nullable', 'integer', 'in:'.implode(',', self::VALID_RANGES)],
        ]);

        return (int) $request->input('range', 30);
    }

    private function buildSeries(string $cacheKey, int $range, string $valueKey, Closure $queryFn): array
    {
        $maxSince = Carbon::now()->subDays(self::MAX_RANGE)->startOfDay();

        $rows = Cache::remember(
            $cacheKey,
            now()->addHours(self::CACHE_TTL_HOURS),
            fn () => $queryFn($maxSince)
        );

        $rangeSince = Carbon::now()->subDays($range)->startOfDay();

        $series = collect(range(0, $range - 1))->map(function ($i) use ($rangeSince, $rows, $valueKey) {
            $date = $rangeSince->copy()->addDays($i)->toDateString();

            return [
                'date' => $date,
                $valueKey => (int) ($rows[$date]->{$valueKey} ?? 0),
            ];
        });

        return [
            'data' => $series->values(),
            'total' => $series->sum($valueKey),
            'range' => $range,
        ];
    }
}
