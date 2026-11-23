<?php

namespace App\Services;

use App\Http\Resources\VideoResource;
use App\Jobs\UpdateUserInterestsJob;
use App\Models\FeedFeedback;
use App\Models\Profile;
use App\Models\UserInterest;
use App\Models\Video;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class ForYouFeedService
{
    private const DEFAULT_LIMIT = 20;

    private const MAX_LIMIT = 50;

    private const FRESHNESS_WEIGHT = 0.25;

    private const ENGAGEMENT_WEIGHT = 0.35;

    private const PERSONALIZATION_WEIGHT = 0.30;

    private const CREATOR_QUALITY_WEIGHT = 0.10;

    private const MAX_AGE_DAYS = 730;

    private const MIN_SCORE_THRESHOLD = 0.1;

    private const DISCOVERY_MIX_RATIO = 0.3;

    private const ENGAGEMENT_SATURATION = 1000;

    private const POOL_MULTIPLIER = 3;

    private const CREATOR_PAGE_CAP = 2;

    private const CREATOR_POOL_CAP = 4;

    private const SCAN_BATCH_SIZE = 500;

    private const MAX_SCAN_BATCHES = 20;

    private const POPULAR_POOL_SIZE = 500;

    private const POPULAR_FRESH_SECONDS = 600;

    private const POPULAR_STALE_SECONDS = 3600;

    private const DISCOVERY_MIN_LIKES = 2;

    private const DISCOVERY_SLICES = 3;

    private const RECYCLE_MIN_WATCH_SECONDS = 3;

    private const SESSION_TTL_MINUTES = 120;

    private const SESSION_MAX_SERVED = 1000;

    private const FEEDBACK_TTL_MINUTES = 30;

    private const FEEDBACK_MAX_VIDEOS = 1000;

    private const FEEDBACK_MAX_CREATORS = 500;

    public function getFeed(
        Profile $profile,
        ?string $cursor = null,
        int $limit = self::DEFAULT_LIMIT
    ): array {
        $limit = max(1, min($limit, self::MAX_LIMIT));
        $feed = $this->buildFeed($profile, $cursor, $limit);

        return [
            'data' => VideoResource::collection($feed['videos']),
            'links' => [
                'first' => null,
                'last' => null,
                'prev' => null,
                'next' => null,
            ],
            'meta' => [
                'path' => url('/api/v1/feed/recommended'),
                'per_page' => $limit,
                'next_cursor' => $feed['has_more'] ? $this->encodeCursor($feed['position']) : null,
                'prev_cursor' => null,
                'has_more' => $feed['has_more'],
            ],
        ];
    }

    private function buildFeed(Profile $profile, ?string $cursor, int $limit): array
    {
        $profileId = (int) $profile->id;
        $cursorId = $cursor ? $this->decodeCursor($cursor) : null;
        $interests = $this->getUserInterests($profileId);
        $feedback = $this->getFeedbackFilters($profileId);
        $hiddenCreators = $feedback['creators'];
        $session = $this->getSession($profileId, $cursorId === null);
        $excluded = array_fill_keys(array_merge($session['served'], $feedback['videos']), true);
        $poolSize = $limit * self::POOL_MULTIPLIER;
        $personalizedCount = $limit - (int) ceil($limit * self::DISCOVERY_MIX_RATIO);

        $walk = $this->getWalkCandidates(
            $profile,
            $interests,
            $hiddenCreators,
            $excluded,
            $session['carry'],
            $cursorId,
            $poolSize
        );
        $popular = $this->getPopularVideos($profile, $hiddenCreators, $excluded, $limit * 2);

        $page = ['limit' => $limit, 'main' => [], 'mix' => [], 'ids' => [], 'creators' => []];

        $this->pick($page, $walk['videos'], 'main', self::CREATOR_PAGE_CAP, $personalizedCount);
        $this->pick($page, $popular, 'mix', self::CREATOR_PAGE_CAP);
        $this->pick($page, $walk['videos'], 'main', self::CREATOR_PAGE_CAP);

        $unseen = [[$walk['videos'], 'main'], [$popular, 'mix']];

        if ($this->isShort($page)) {
            $discovery = $this->getDiscoveryVideos($profile, $hiddenCreators, $excluded + $page['ids'], $limit);
            $this->pick($page, $discovery, 'mix', self::CREATOR_PAGE_CAP);
            $unseen[] = [$discovery, 'mix'];
        }

        if ($this->isShort($page)) {
            foreach ($unseen as [$candidates, $slot]) {
                $this->pick($page, $candidates, $slot, PHP_INT_MAX);
            }
        }

        $walkPicks = $session['walk_picks'] + $this->countPicked($page, $walk['videos']);

        if ($this->isShort($page) && $walk['exhausted'] && $cursorId !== null && $walkPicks > 0) {
            $walk = $this->getWalkCandidates(
                $profile,
                $interests,
                $hiddenCreators,
                $excluded + $page['ids'],
                [],
                null,
                $poolSize
            );
            $this->pick($page, $walk['videos'], 'main', self::CREATOR_PAGE_CAP);
            $this->pick($page, $walk['videos'], 'main', PHP_INT_MAX);
            $walkPicks = $this->countPicked($page, $walk['videos']);
        }

        if ($this->isShort($page) && $this->recycleEnabled()) {
            $recycled = $this->getRecycledVideos($profile, $hiddenCreators, $excluded + $page['ids'], $limit);
            $this->pick($page, $recycled, 'mix', self::CREATOR_PAGE_CAP);
            $this->pick($page, $recycled, 'mix', PHP_INT_MAX);
        }

        $carry = [];
        foreach ($walk['videos'] as $video) {
            if (! isset($page['ids'][(int) $video->id])) {
                $carry[(int) $video->id] = (string) $video->profile_id;
            }
        }

        $videos = $this->spreadCreators(
            $this->interleaveVideos(collect($page['main']), collect($page['mix']), $limit)
        );

        $this->putSession($profileId, [
            'served' => array_merge($session['served'], array_keys($page['ids'])),
            'carry' => $carry,
            'walk_picks' => $walkPicks,
        ]);

        return [
            'videos' => $videos,
            'position' => $walk['floor'] ?? $cursorId ?? 0,
            'has_more' => ! $walk['exhausted'] || ! empty($carry) || $videos->count() >= $limit,
        ];
    }

    private function countPicked(array $page, Collection $videos): int
    {
        return $videos
            ->filter(fn (Video $video) => isset($page['ids'][(int) $video->id]))
            ->count();
    }

    private function visibleVideos(Profile $profile, array $hiddenCreators): Builder
    {
        return Video::query()
            ->where('videos.status', 2)
            ->where('videos.visibility', 1)
            ->where('videos.created_at', '>=', now()->subDays(self::MAX_AGE_DAYS))
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

    private function loadVideos(Profile $profile, array $hiddenCreators, array $ids): Collection
    {
        if (empty($ids)) {
            return collect();
        }

        $videos = $this->visibleVideos($profile, $hiddenCreators)
            ->whereIn('videos.id', $ids)
            ->select('videos.*')
            ->with([
                'profile:id,username,trust_level',
                'hashtags:hashtags.id,hashtags.name',
            ])
            ->get()
            ->keyBy('id');

        return collect($ids)
            ->map(fn ($id) => $videos->get($id))
            ->filter()
            ->values();
    }

    private function getWalkCandidates(
        Profile $profile,
        Collection $interests,
        array $hiddenCreators,
        array $excluded,
        array $carry,
        ?int $cursorId,
        int $poolSize
    ): array {
        $carry = array_diff_key($carry, $excluded);
        $unseenCarry = array_flip(
            app(ImpressionBloomFilterService::class)->filterIds((int) $profile->id, array_keys($carry))
        );
        $carry = array_intersect_key($carry, $unseenCarry);

        $scan = $this->scanCandidates(
            $profile,
            $hiddenCreators,
            $excluded + $carry,
            array_count_values($carry),
            $cursorId,
            $poolSize - count($carry)
        );

        $videos = $this->loadVideos(
            $profile,
            $hiddenCreators,
            array_merge(array_keys($carry), $scan['ids'])
        );

        $scores = [];
        foreach ($videos as $video) {
            $scores[(int) $video->id] = $this->calculateVideoScore($video, $interests);
        }

        return [
            'videos' => $videos
                ->filter(fn (Video $video) => $scores[(int) $video->id] >= self::MIN_SCORE_THRESHOLD)
                ->sortByDesc(fn (Video $video) => $scores[(int) $video->id])
                ->values(),
            'floor' => $scan['floor'],
            'exhausted' => $scan['exhausted'],
        ];
    }

    private function scanCandidates(
        Profile $profile,
        array $hiddenCreators,
        array $excluded,
        array $creatorCounts,
        ?int $cursorId,
        int $target
    ): array {
        if ($target <= 0) {
            return ['ids' => [], 'floor' => null, 'exhausted' => false];
        }

        $bloom = app(ImpressionBloomFilterService::class);
        $ids = [];
        $overflow = [];
        $overflowCount = 0;
        $floor = null;
        $exhausted = false;
        $lastId = $cursorId;

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

            $unseen = array_flip($bloom->filterIds((int) $profile->id, $rows->pluck('id')->all()));
            $full = false;

            foreach ($rows as $row) {
                $id = (int) $row->id;
                $lastId = $id;
                $floor = $id;

                if (! isset($unseen[$id]) || isset($excluded[$id])) {
                    continue;
                }

                $creator = (string) $row->profile_id;

                if (($creatorCounts[$creator] ?? 0) >= self::CREATOR_POOL_CAP) {
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

    private function getPopularVideos(
        Profile $profile,
        array $hiddenCreators,
        array $excluded,
        int $limit
    ): Collection {
        $candidates = array_values(array_filter(
            $this->getPopularPool(),
            fn ($id) => ! isset($excluded[$id])
        ));

        $unseen = app(ImpressionBloomFilterService::class)->filterIds((int) $profile->id, $candidates);

        return $this->loadVideos($profile, $hiddenCreators, array_slice($unseen, 0, $limit));
    }

    private function getPopularPool(): array
    {
        return Cache::flexible(
            'fyf:popular:pool',
            [self::POPULAR_FRESH_SECONDS, self::POPULAR_STALE_SECONDS],
            fn () => DB::table('videos')
                ->where('status', 2)
                ->where('visibility', 1)
                ->where('created_at', '>=', now()->subDays(self::MAX_AGE_DAYS))
                ->orderByRaw('(
                    (likes * 2) +
                    (comments * 3) +
                    (shares * 4) +
                    (bookmarks * 3) +
                    (views * 0.1)
                ) / (TIMESTAMPDIFF(HOUR, created_at, NOW()) + 1) DESC')
                ->limit(self::POPULAR_POOL_SIZE)
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all()
        );
    }

    private function getDiscoveryVideos(
        Profile $profile,
        array $hiddenCreators,
        array $excluded,
        int $limit
    ): Collection {
        [$minId, $maxId] = $this->getDiscoveryBounds();

        if ($maxId <= 0) {
            return collect();
        }

        $ids = [];

        for ($slice = 0; $slice < self::DISCOVERY_SLICES; $slice++) {
            $rows = $this->visibleVideos($profile, $hiddenCreators)
                ->where('videos.likes', '>=', self::DISCOVERY_MIN_LIKES)
                ->where('videos.id', '<=', random_int($minId, $maxId))
                ->orderByDesc('videos.id')
                ->limit($limit)
                ->toBase()
                ->pluck('videos.id');

            foreach ($rows as $id) {
                if (! isset($excluded[(int) $id])) {
                    $ids[(int) $id] = true;
                }
            }
        }

        $unseen = app(ImpressionBloomFilterService::class)->filterIds((int) $profile->id, array_keys($ids));
        shuffle($unseen);

        return $this->loadVideos($profile, $hiddenCreators, array_slice($unseen, 0, $limit));
    }

    private function getDiscoveryBounds(): array
    {
        return Cache::flexible(
            'fyf:discovery:bounds',
            [self::POPULAR_FRESH_SECONDS, self::POPULAR_STALE_SECONDS],
            function () {
                $bounds = DB::table('videos')
                    ->where('status', 2)
                    ->where('visibility', 1)
                    ->where('created_at', '>=', now()->subDays(self::MAX_AGE_DAYS))
                    ->selectRaw('MIN(id) as min_id, MAX(id) as max_id')
                    ->first();

                return [(int) ($bounds->min_id ?? 0), (int) ($bounds->max_id ?? 0)];
            }
        );
    }

    private function getRecycledVideos(
        Profile $profile,
        array $hiddenCreators,
        array $excluded,
        int $limit
    ): Collection {
        $ids = $this->visibleVideos($profile, $hiddenCreators)
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

        return $this->loadVideos($profile, $hiddenCreators, $ids);
    }

    private function recycleEnabled(): bool
    {
        return (bool) config('loops.feed.fyf.recycle.enabled', true);
    }

    private function recycleAfterDays(): int
    {
        return max(1, (int) config('loops.feed.fyf.recycle.after_days', 30));
    }

    private function pick(array &$page, Collection $candidates, string $slot, int $creatorCap, ?int $max = null): void
    {
        $room = $page['limit'] - count($page['main']) - count($page['mix']);
        $take = $max === null ? $room : min($room, $max);

        foreach ($candidates as $video) {
            if ($take <= 0) {
                break;
            }

            $id = (int) $video->id;
            $creator = (string) $video->profile_id;

            if (isset($page['ids'][$id]) || ($page['creators'][$creator] ?? 0) >= $creatorCap) {
                continue;
            }

            $page['ids'][$id] = true;
            $page['creators'][$creator] = ($page['creators'][$creator] ?? 0) + 1;
            $page[$slot][] = $video;
            $take--;
        }
    }

    private function isShort(array $page): bool
    {
        return count($page['main']) + count($page['mix']) < $page['limit'];
    }

    private function interleaveVideos(Collection $personalized, Collection $discovery, int $limit): Collection
    {
        $result = collect();
        $pIndex = 0;
        $dIndex = 0;

        while ($result->count() < $limit) {
            for ($i = 0; $i < 2 && $pIndex < $personalized->count() && $result->count() < $limit; $i++) {
                $result->push($personalized[$pIndex++]);
            }

            if ($dIndex < $discovery->count() && $result->count() < $limit) {
                $result->push($discovery[$dIndex++]);
            }

            if ($pIndex >= $personalized->count() && $dIndex >= $discovery->count()) {
                break;
            }
        }

        return $result->take($limit)->values();
    }

    private function spreadCreators(Collection $videos): Collection
    {
        $queue = $videos->values()->all();
        $result = [];
        $lastCreator = null;

        while (! empty($queue)) {
            $index = 0;

            foreach ($queue as $i => $video) {
                if ((string) $video->profile_id !== $lastCreator) {
                    $index = $i;
                    break;
                }
            }

            $video = $queue[$index];
            array_splice($queue, $index, 1);
            $result[] = $video;
            $lastCreator = (string) $video->profile_id;
        }

        return collect($result);
    }

    private function calculateVideoScore(Video $video, Collection $interests): float
    {
        $freshnessScore = $this->calculateFreshnessScore($video);
        $engagementScore = $this->calculateEngagementScore($video);
        $personalizationScore = $this->calculatePersonalizationScore($video, $interests);
        $creatorQualityScore = $this->calculateCreatorQualityScore($video);

        $totalScore = (
            ($freshnessScore * self::FRESHNESS_WEIGHT) +
            ($engagementScore * self::ENGAGEMENT_WEIGHT) +
            ($personalizationScore * self::PERSONALIZATION_WEIGHT) +
            ($creatorQualityScore * self::CREATOR_QUALITY_WEIGHT)
        );

        return round($totalScore, 4);
    }

    private function calculateFreshnessScore(Video $video): float
    {
        $ageInHours = now()->diffInHours($video->created_at);
        $maxAgeHours = self::MAX_AGE_DAYS * 24;

        return max(0, 1 - pow($ageInHours / $maxAgeHours, 2));
    }

    private function calculateEngagementScore(Video $video): float
    {
        $ageInDays = max(1, (int) abs(now()->diffInDays($video->created_at)) + 1);

        $interactions = ($video->likes * 2) +
            ($video->comments * 3) +
            ($video->shares * 4) +
            ($video->bookmarks * 3) +
            ($video->views * 0.1);

        $volume = min(1, log1p(max(0, $interactions)) / log1p(self::ENGAGEMENT_SATURATION));

        return $volume / log($ageInDays + 2);
    }

    private function calculatePersonalizationScore(Video $video, Collection $interests): float
    {
        if ($interests->isEmpty()) {
            return 0.5;
        }

        $score = 0;
        $maxScore = 0;

        $creatorInterest = $interests->first(function ($interest) use ($video) {
            return $interest->interest_type === UserInterest::TYPE_CREATOR
                && $interest->interest_value == $video->profile_id;
        });

        if ($creatorInterest) {
            $score += ($creatorInterest->score / 100) * 0.5;
        }
        $maxScore += 0.5;

        $videoHashtags = $this->getVideoHashtags($video);

        if ($videoHashtags->isNotEmpty()) {
            $hashtagMatches = $interests->filter(function ($interest) use ($videoHashtags) {
                return $interest->interest_type === UserInterest::TYPE_HASHTAG
                    && $videoHashtags->contains('name', $interest->interest_value);
            });

            if ($hashtagMatches->isNotEmpty()) {
                $avgHashtagScore = $hashtagMatches->avg('score') / 100;
                $score += $avgHashtagScore * 0.3;
            }
        }
        $maxScore += 0.3;

        if ($video->category) {
            $categoryInterest = $interests->first(function ($interest) use ($video) {
                return $interest->interest_type === UserInterest::TYPE_CATEGORY
                    && $interest->interest_value === $video->category;
            });

            if ($categoryInterest) {
                $score += ($categoryInterest->score / 100) * 0.2;
            }
        }
        $maxScore += 0.2;

        // @phpstan-ignore-next-line
        return $maxScore > 0 ? $score / $maxScore : 0.5;
    }

    private function getVideoHashtags(Video $video): Collection
    {
        if ($video->relationLoaded('hashtags')) {
            return $video->hashtags;
        }

        return Cache::remember(
            "video_hashtags:{$video->id}",
            7200,
            fn () => $video->hashtags()->get(['hashtags.id', 'hashtags.name'])
        );
    }

    private function calculateCreatorQualityScore(Video $video): float
    {
        if (! $video->profile) {
            return 0.5;
        }

        $trustScore = ($video->profile->trust_level ?? 5) / 10;

        return $trustScore;
    }

    private function getUserInterests(int $profileId): Collection
    {
        return Cache::remember(
            "fyf:user_interests:{$profileId}",
            now()->addMinutes(30),
            fn () => UserInterest::where('profile_id', $profileId)
                ->where('score', '>', 0)
                ->orderByDesc('score')
                ->limit(50)
                ->get()
        );
    }

    public function forgetUserInterests(int $profileId): void
    {
        Cache::forget("fyf:user_interests:{$profileId}");
    }

    private function getFeedbackFilters(int $profileId): array
    {
        return Cache::remember(
            "fyf:feedback:{$profileId}",
            now()->addMinutes(self::FEEDBACK_TTL_MINUTES),
            function () use ($profileId) {
                $videos = FeedFeedback::where('profile_id', $profileId)
                    ->whereIn('feedback_type', [
                        FeedFeedback::TYPE_DISLIKE,
                        FeedFeedback::TYPE_NOT_INTERESTED,
                        FeedFeedback::TYPE_HIDE_CREATOR,
                    ])
                    ->orderByDesc('id')
                    ->limit(self::FEEDBACK_MAX_VIDEOS)
                    ->pluck('video_id')
                    ->map(fn ($id) => (int) $id)
                    ->unique()
                    ->values()
                    ->all();

                $creators = DB::table('feed_feedback')
                    ->join('videos', 'videos.id', '=', 'feed_feedback.video_id')
                    ->where('feed_feedback.profile_id', $profileId)
                    ->where('feed_feedback.feedback_type', FeedFeedback::TYPE_HIDE_CREATOR)
                    ->distinct()
                    ->limit(self::FEEDBACK_MAX_CREATORS)
                    ->pluck('videos.profile_id')
                    ->map(fn ($id) => (int) $id)
                    ->all();

                return ['videos' => $videos, 'creators' => $creators];
            }
        );
    }

    public function forgetFeedbackFilters(int $profileId): void
    {
        Cache::forget("fyf:feedback:{$profileId}");
    }

    private function getSession(int $profileId, bool $fresh): array
    {
        $session = $fresh ? null : Cache::get("fyf:session:{$profileId}");

        return [
            'served' => is_array($session['served'] ?? null) ? $session['served'] : [],
            'carry' => is_array($session['carry'] ?? null) ? $session['carry'] : [],
            'walk_picks' => (int) ($session['walk_picks'] ?? 0),
        ];
    }

    private function putSession(int $profileId, array $session): void
    {
        $session['served'] = array_slice($session['served'], -self::SESSION_MAX_SERVED);

        Cache::put(
            "fyf:session:{$profileId}",
            $session,
            now()->addMinutes(self::SESSION_TTL_MINUTES)
        );
    }

    private function encodeCursor(int $position): string
    {
        return base64_encode('0|'.$position);
    }

    private function decodeCursor(string $cursor): ?int
    {
        $decoded = base64_decode($cursor, true);

        if (! $decoded || ! str_contains($decoded, '|')) {
            return null;
        }

        $id = explode('|', $decoded, 2)[1];

        return is_numeric($id) ? (int) $id : null;
    }

    public function recordImpression(
        int $profileId,
        int $videoId,
        int $watchDuration = 0,
        bool $completed = false
    ): void {
        $watchDuration = max(0, min($watchDuration, 65535));

        DB::statement(
            '
                INSERT INTO feed_impressions (
                    profile_id,
                    video_id,
                    viewed_at,
                    watch_duration,
                    completed
                )
                VALUES (?, ?, NOW(), ?, ?)
                AS new
                ON DUPLICATE KEY UPDATE
                    viewed_at = new.viewed_at,
                    watch_duration = GREATEST(feed_impressions.watch_duration, new.watch_duration),
                    completed = GREATEST(feed_impressions.completed, new.completed)
                ',
            [
                $profileId,
                $videoId,
                $watchDuration,
                $completed ? 1 : 0,
            ]
        );

        app(ImpressionBloomFilterService::class)->add($profileId, $videoId);
    }

    public function recordFeedback(
        int $profileId,
        int $videoId,
        string $feedbackType
    ): void {
        $feedback = FeedFeedback::firstOrCreate([
            'profile_id' => $profileId,
            'video_id' => $videoId,
            'feedback_type' => $feedbackType,
        ]);

        $this->forgetFeedbackFilters($profileId);

        if ($feedback->wasRecentlyCreated) {
            UpdateUserInterestsJob::dispatch($profileId);
        }
    }

    public function removeFeedback(int $profileId, int $videoId): void
    {
        FeedFeedback::where('profile_id', $profileId)
            ->where('video_id', $videoId)
            ->delete();

        $this->forgetFeedbackFilters($profileId);

        UpdateUserInterestsJob::dispatch($profileId);
    }
}
