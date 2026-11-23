<?php

namespace App\Services;

use App\Models\Profile;
use App\Models\Video;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class ForYouCandidateService
{
    private const FOLLOWING_DAYS = 90;

    private const FOLLOWING_LIMIT = 150;

    private const FOLLOWING_SET_LIMIT = 5000;

    private const CREATOR_TOP = 20;

    private const CREATOR_PER_CREATOR = 10;

    private const HASHTAG_TOP = 8;

    private const HASHTAG_PER_TAG = 40;

    private const FRESH_POOL = 1000;

    private const FRESH_TAKE = 150;

    private const TRENDING_DAYS = 14;

    private const TRENDING_POOL = 300;

    private const TRENDING_TAKE = 100;

    private const EVERGREEN_POOL = 1000;

    private const EVERGREEN_TAKE = 100;

    private const EXPLORE_DAYS = 7;

    private const EXPLORE_MAX_IMPRESSIONS = 30;

    private const EXPLORE_POOL = 500;

    private const EXPLORE_TAKE = 30;

    private const WALK_POOL = 60;

    private const WALK_CREATOR_CAP = 4;

    private const SCAN_BATCH_SIZE = 500;

    private const MAX_SCAN_BATCHES = 20;

    private const RECYCLE_MIN_WATCH_SECONDS = 3;

    private const ROW_CHUNK_SIZE = 500;

    public function __construct(
        private ImpressionBloomFilterService $bloom
    ) {}

    public function visibleVideos(Profile $profile, array $hiddenCreators): Builder
    {
        return Video::query()
            ->where('videos.status', 2)
            ->where('videos.visibility', 1)
            ->where('videos.created_at', '>=', now()->subDays($this->maxAgeDays()))
            ->where('videos.profile_id', '!=', $profile->id)
            ->when($profile->hide_ai, function ($q) {
                $q->where('videos.contains_ai', false);
            })
            ->when(! empty($hiddenCreators), function ($q) use ($hiddenCreators) {
                $q->whereNotIn('videos.profile_id', $hiddenCreators);
            })
            ->leftJoin('user_filters', function ($join) use ($profile) {
                $join->on('videos.profile_id', '=', 'user_filters.account_id')
                    ->where('user_filters.profile_id', '=', $profile->id);
            })
            ->whereNull('user_filters.id');
    }

    public function gather(Profile $profile, array $context, array $excluded, array $walkState): array
    {
        $profileId = (int) $profile->id;

        $sources = [
            'following' => $this->followingVideoIds($profileId),
            'creators' => $this->creatorVideoIds($profileId, $this->top($context['creators'], self::CREATOR_TOP)),
            'hashtags' => $this->hashtagVideoIds($profileId, $this->top($context['hashtags'], self::HASHTAG_TOP)),
            'fresh' => $this->freshPool(),
            'trending' => $this->trendingPool(),
            'evergreen' => $this->evergreenPool(),
            'explore' => $this->explorePool(),
        ];

        $pending = [];
        foreach ($sources as $ids) {
            foreach ($ids as $id) {
                if (! isset($excluded[$id])) {
                    $pending[$id] = true;
                }
            }
        }

        $unseen = array_flip($this->bloom->filterIds($profileId, array_keys($pending)));

        foreach ($sources as $name => $ids) {
            $sources[$name] = array_values(array_filter($ids, fn ($id) => isset($unseen[$id])));
        }

        shuffle($sources['explore']);

        $sources['fresh'] = array_slice($sources['fresh'], 0, self::FRESH_TAKE);
        $sources['trending'] = array_slice($sources['trending'], 0, self::TRENDING_TAKE);
        $sources['evergreen'] = array_slice($sources['evergreen'], 0, self::EVERGREEN_TAKE);
        $sources['explore'] = array_slice($sources['explore'], 0, self::EXPLORE_TAKE);

        $walk = $this->walk($profile, $context['hidden_creators'], $excluded, $walkState);
        $sources['walk'] = $walk['ids'];

        $ids = array_keys(array_fill_keys(array_merge(...array_values($sources)), true));

        return [
            'ids' => $ids,
            'served' => array_fill_keys($this->bloom->servedIds($profileId, $ids), true),
            'lanes' => [
                'trending' => array_fill_keys(array_merge($sources['trending'], $sources['evergreen']), true),
                'explore' => array_fill_keys($sources['explore'], true),
            ],
            'walk' => $walk,
        ];
    }

    public function rows(Profile $profile, array $hiddenCreators, array $ids, array $hashtagNames): array
    {
        $rows = [];

        foreach (array_chunk($ids, self::ROW_CHUNK_SIZE) as $chunk) {
            $videos = $this->visibleVideos($profile, $hiddenCreators)
                ->leftJoin('video_feed_stats', 'video_feed_stats.video_id', '=', 'videos.id')
                ->whereIn('videos.id', $chunk)
                ->toBase()
                ->get([
                    'videos.id',
                    'videos.profile_id',
                    'videos.likes',
                    'videos.comments',
                    'videos.shares',
                    'videos.bookmarks',
                    'videos.created_at',
                    'video_feed_stats.impressions',
                    'video_feed_stats.completions',
                ]);

            foreach ($videos as $video) {
                $rows[(int) $video->id] = [
                    'creator' => (string) $video->profile_id,
                    'interactions' => ($video->likes * 2) + ($video->comments * 3) + ($video->shares * 4) + ($video->bookmarks * 3),
                    'impressions' => (int) $video->impressions,
                    'completions' => (int) $video->completions,
                    'created_at' => strtotime($video->created_at),
                    'tags' => [],
                    'trust' => 5,
                ];
            }
        }

        if (empty($rows)) {
            return [];
        }

        if (! empty($hashtagNames)) {
            foreach (array_chunk(array_keys($rows), self::ROW_CHUNK_SIZE) as $chunk) {
                $tags = DB::table('video_hashtags')
                    ->join('hashtags', 'hashtags.id', '=', 'video_hashtags.hashtag_id')
                    ->whereIn('video_hashtags.video_id', $chunk)
                    ->whereIn('hashtags.name', $hashtagNames)
                    ->get(['video_hashtags.video_id', 'hashtags.name']);

                foreach ($tags as $tag) {
                    $rows[(int) $tag->video_id]['tags'][] = (string) $tag->name;
                }
            }
        }

        $trust = DB::table('profiles')
            ->whereIn('id', array_unique(array_column($rows, 'creator')))
            ->where('trust_level', '!=', 5)
            ->pluck('trust_level', 'id');

        if ($trust->isNotEmpty()) {
            foreach ($rows as $id => $row) {
                if ($trust->has($row['creator'])) {
                    $rows[$id]['trust'] = (int) $trust->get($row['creator']);
                }
            }
        }

        return $rows;
    }

    public function followingSet(int $profileId): array
    {
        return Cache::remember(
            "fyf:following:{$profileId}",
            now()->addMinutes(10),
            fn () => DB::table('followers')
                ->where('profile_id', $profileId)
                ->limit(self::FOLLOWING_SET_LIMIT)
                ->pluck('following_id')
                ->map(fn ($id) => (string) $id)
                ->all()
        );
    }

    public function recycled(Profile $profile, array $hiddenCreators, array $excluded, int $limit): array
    {
        return $this->visibleVideos($profile, $hiddenCreators)
            ->join('feed_impressions', function ($join) use ($profile) {
                $join->on('feed_impressions.video_id', '=', 'videos.id')
                    ->where('feed_impressions.profile_id', '=', $profile->id);
            })
            ->where('feed_impressions.viewed_at', '<', now()->subDays($this->recycleAfterDays()))
            ->where(function ($q) {
                $q->where('feed_impressions.completed', true)
                    ->orWhere('feed_impressions.watch_duration', '>=', self::RECYCLE_MIN_WATCH_SECONDS);
            })
            ->when(! empty($excluded), function ($q) use ($excluded) {
                $q->whereNotIn('videos.id', array_keys($excluded));
            })
            ->orderByDesc('feed_impressions.completed')
            ->orderBy('feed_impressions.viewed_at')
            ->limit($limit)
            ->toBase()
            ->pluck('videos.id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    private function walk(Profile $profile, array $hiddenCreators, array $excluded, array $state): array
    {
        $carry = array_diff_key($state['carry'], $excluded);
        $carry = array_intersect_key(
            $carry,
            array_flip($this->bloom->filterIds((int) $profile->id, array_keys($carry)))
        );

        if ($state['exhausted']) {
            return ['ids' => array_keys($carry), 'position' => $state['position'], 'exhausted' => true];
        }

        $scan = $this->scan(
            $profile,
            $hiddenCreators,
            $excluded + $carry,
            array_count_values($carry),
            $state['position'],
            self::WALK_POOL - count($carry)
        );

        return [
            'ids' => array_merge(array_keys($carry), $scan['ids']),
            'position' => $scan['floor'] ?? $state['position'],
            'exhausted' => $scan['exhausted'],
        ];
    }

    private function scan(
        Profile $profile,
        array $hiddenCreators,
        array $excluded,
        array $creatorCounts,
        ?int $position,
        int $target
    ): array {
        if ($target <= 0) {
            return ['ids' => [], 'floor' => null, 'exhausted' => false];
        }

        $ids = [];
        $overflow = [];
        $overflowCount = 0;
        $floor = null;
        $exhausted = false;
        $lastId = $position;

        for ($batch = 0; $batch < self::MAX_SCAN_BATCHES; $batch++) {
            $rows = $this->visibleVideos($profile, $hiddenCreators)
                ->when($lastId !== null, function ($q) use ($lastId) {
                    $q->where('videos.id', '<', $lastId);
                })
                ->orderByDesc('videos.id')
                ->limit(self::SCAN_BATCH_SIZE)
                ->toBase()
                ->get(['videos.id', 'videos.profile_id']);

            if ($rows->isEmpty()) {
                $exhausted = true;
                break;
            }

            $unseen = array_flip($this->bloom->filterIds((int) $profile->id, $rows->pluck('id')->all()));
            $full = false;

            foreach ($rows as $row) {
                $id = (int) $row->id;
                $lastId = $id;
                $floor = $id;

                if (! isset($unseen[$id]) || isset($excluded[$id])) {
                    continue;
                }

                $creator = (string) $row->profile_id;

                if (($creatorCounts[$creator] ?? 0) >= self::WALK_CREATOR_CAP) {
                    $overflowCount++;

                    if (count($overflow) < $target) {
                        $overflow[] = $id;
                    }

                    continue;
                }

                $creatorCounts[$creator] = ($creatorCounts[$creator] ?? 0) + 1;
                $ids[] = $id;

                if (count($ids) >= $target) {
                    $full = true;
                    break;
                }
            }

            if ($full) {
                break;
            }

            if ($rows->count() < self::SCAN_BATCH_SIZE) {
                $exhausted = true;
                break;
            }
        }

        if (count($ids) < $target && ! empty($overflow)) {
            $admitted = array_slice($overflow, 0, $target - count($ids));

            if ($overflowCount > count($admitted)) {
                $floor = end($admitted);
                $exhausted = false;
            }

            $ids = array_merge($ids, $admitted);
        }

        return ['ids' => $ids, 'floor' => $floor, 'exhausted' => $exhausted];
    }

    private function followingVideoIds(int $profileId): array
    {
        return DB::table('videos')
            ->join('followers', function ($join) use ($profileId) {
                $join->on('followers.following_id', '=', 'videos.profile_id')
                    ->where('followers.profile_id', '=', $profileId);
            })
            ->where('videos.status', 2)
            ->where('videos.visibility', 1)
            ->where('videos.id', '>', $this->idFloor(self::FOLLOWING_DAYS))
            ->whereNotExists($this->impressionOf($profileId, 'videos.id'))
            ->orderByDesc('videos.id')
            ->limit(self::FOLLOWING_LIMIT)
            ->pluck('videos.id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    private function creatorVideoIds(int $profileId, array $creatorIds): array
    {
        $query = null;

        foreach ($creatorIds as $creatorId) {
            $recent = DB::table('videos')
                ->select('id')
                ->where('profile_id', $creatorId)
                ->where('status', 2)
                ->where('visibility', 1)
                ->where('id', '>', $this->idFloor($this->maxAgeDays()))
                ->whereNotExists($this->impressionOf($profileId, 'videos.id'))
                ->orderByDesc('id')
                ->limit(self::CREATOR_PER_CREATOR);

            $query = $query ? $query->unionAll($recent) : $recent;
        }

        if (! $query) {
            return [];
        }

        return $query->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    private function hashtagVideoIds(int $profileId, array $names): array
    {
        if (empty($names)) {
            return [];
        }

        $query = null;

        foreach (DB::table('hashtags')->whereIn('name', $names)->pluck('id') as $hashtagId) {
            $tagged = DB::table('video_hashtags')
                ->select('video_id')
                ->where('hashtag_id', $hashtagId)
                ->whereNotExists($this->impressionOf($profileId, 'video_hashtags.video_id'))
                ->orderByDesc('id')
                ->limit(self::HASHTAG_PER_TAG);

            $query = $query ? $query->unionAll($tagged) : $tagged;
        }

        if (! $query) {
            return [];
        }

        return $query->pluck('video_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    private function freshPool(): array
    {
        return Cache::flexible('fyf:pool:fresh', [60, 600], fn () => DB::table('videos')
            ->where('status', 2)
            ->where('visibility', 1)
            ->orderByDesc('id')
            ->limit(self::FRESH_POOL)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all());
    }

    private function trendingPool(): array
    {
        return Cache::flexible('fyf:pool:trending', [300, 1800], fn () => DB::table('videos')
            ->where('status', 2)
            ->where('visibility', 1)
            ->where('id', '>', $this->idFloor(self::TRENDING_DAYS))
            ->where('created_at', '>=', now()->subDays(self::TRENDING_DAYS))
            ->orderByRaw('(
                (likes * 2) +
                (comments * 3) +
                (shares * 4) +
                (bookmarks * 3)
            ) / POW(GREATEST(TIMESTAMPDIFF(HOUR, created_at, NOW()), 0) + 2, 1.5) DESC')
            ->limit(self::TRENDING_POOL)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all());
    }

    private function evergreenPool(): array
    {
        return Cache::flexible('fyf:pool:evergreen', [1800, 7200], fn () => DB::table('videos')
            ->where('status', 2)
            ->where('visibility', 1)
            ->where('created_at', '>=', now()->subDays($this->maxAgeDays()))
            ->where('created_at', '<', now()->subDays(self::TRENDING_DAYS))
            ->orderByRaw('(likes * 2) + (comments * 3) + (shares * 4) + (bookmarks * 3) DESC')
            ->limit(self::EVERGREEN_POOL)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all());
    }

    private function explorePool(): array
    {
        return Cache::flexible('fyf:pool:explore', [120, 900], fn () => DB::table('videos')
            ->leftJoin('video_feed_stats', 'video_feed_stats.video_id', '=', 'videos.id')
            ->where('videos.status', 2)
            ->where('videos.visibility', 1)
            ->where('videos.id', '>', $this->idFloor(self::EXPLORE_DAYS))
            ->where('videos.created_at', '>=', now()->subDays(self::EXPLORE_DAYS))
            ->where(function ($q) {
                $q->whereNull('video_feed_stats.impressions')
                    ->orWhere('video_feed_stats.impressions', '<', self::EXPLORE_MAX_IMPRESSIONS);
            })
            ->orderByDesc('videos.id')
            ->limit(self::EXPLORE_POOL)
            ->pluck('videos.id')
            ->map(fn ($id) => (int) $id)
            ->all());
    }

    private function impressionOf(int $profileId, string $videoColumn): Closure
    {
        return function ($query) use ($profileId, $videoColumn) {
            $query->selectRaw('1')
                ->from('feed_impressions')
                ->whereColumn('feed_impressions.video_id', $videoColumn)
                ->where('feed_impressions.profile_id', $profileId);
        };
    }

    private function top(array $scores, int $limit): array
    {
        arsort($scores);

        return array_map('strval', array_slice(array_keys($scores), 0, $limit));
    }

    private function idFloor(int $days): int
    {
        return (int) SnowflakeService::byDate(now()->subDays($days));
    }

    private function maxAgeDays(): int
    {
        return max(1, (int) config('loops.feed.fyf.max_age_days', 730));
    }

    private function recycleAfterDays(): int
    {
        return max(1, (int) config('loops.feed.fyf.recycle.after_days', 30));
    }
}
