<?php

namespace App\Services;

use App\Http\Resources\VideoResource;
use App\Models\FeedFeedback;
use App\Models\Profile;
use App\Models\UserInterest;
use App\Models\Video;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class ForYouFeedService
{
    private const DEFAULT_LIMIT = 20;

    private const FRESHNESS_WEIGHT = 0.25;

    private const ENGAGEMENT_WEIGHT = 0.35;

    private const PERSONALIZATION_WEIGHT = 0.30;

    private const CREATOR_QUALITY_WEIGHT = 0.10;

    private const MAX_AGE_DAYS = 730;

    private const MIN_SCORE_THRESHOLD = 0.1;

    private const MIN_RESULTS = 20;

    private const DISCOVERY_MIX_RATIO = 0.3;

    public function getFeed(
        Profile $profile,
        ?string $cursor = null,
        int $limit = self::DEFAULT_LIMIT
    ): array {
        $videos = $this->buildFeedQuery($profile, $cursor, $limit);

        return [
            'data' => VideoResource::collection($videos),
            'links' => [
                'first' => null,
                'last' => null,
                'prev' => null,
                'next' => null,
            ],
            'meta' => [
                'path' => url('/api/v1/feed/recommended'),
                'per_page' => $limit,
                'next_cursor' => $this->generateCursor($videos),
                'prev_cursor' => null,
                'has_more' => count($videos) === $limit,
            ],
        ];
    }

    private function buildFeedQuery(Profile $profile, ?string $cursor, int $limit): Collection
    {
        $interests = $this->getUserInterests($profile->id);
        $isNewUser = $interests->isEmpty();

        $cursorScore = null;
        $cursorId = null;
        if ($cursor) {
            [$cursorScore, $cursorId] = $this->decodeCursor($cursor);
        }

        $discoveryCount = (int) ceil($limit * self::DISCOVERY_MIX_RATIO);
        $personalizedCount = $limit - $discoveryCount;

        $personalizedVideos = $this->getPersonalizedVideos(
            $profile,
            $interests,
            $cursorScore,
            $cursorId,
            $isNewUser ? $limit : $personalizedCount
        );

        if ($personalizedVideos->count() < self::MIN_RESULTS || $isNewUser) {
            $popularVideos = $this->getPopularVideos(
                $profile,
                $cursorId,
                $limit - $personalizedVideos->count(),
                $personalizedVideos->pluck('id')->toArray()
            );

            $allVideos = $personalizedVideos->concat($popularVideos)
                ->unique('id')
                ->sortByDesc('feed_score')
                ->take($limit)
                ->values();

            return $allVideos;
        }
        // @phpstan-ignore-next-line
        if (! $isNewUser && $discoveryCount > 0) {
            $discoveryVideos = $this->getDiscoveryVideos(
                $profile,
                $personalizedVideos->pluck('id')->toArray(),
                $discoveryCount
            );

            return $this->interleaveVideos($personalizedVideos, $discoveryVideos, $limit);
        }

        return $personalizedVideos->take($limit)->values();
    }

    private function applyUserFilters($query, int $profileId)
    {
        return $query->leftJoin('user_filters', function ($join) use ($profileId) {
            $join->on('videos.profile_id', '=', 'user_filters.account_id')
                ->where('user_filters.profile_id', '=', $profileId);
        })->whereNull('user_filters.id');
    }

    private function applyContentPreferences($query, Profile $profile)
    {
        return $query->when($profile->hide_ai, function ($q) {
            $q->where('videos.contains_ai', false);
        });
    }

    private function getPersonalizedVideos(
        Profile $profile,
        Collection $interests,
        ?float $cursorScore,
        ?int $cursorId,
        int $limit
    ): Collection {
        $scoredVideos = collect();
        $lastId = $cursorId;
        $maxAttempts = 5;
        $attempts = 0;
        $batchMultiplier = 3;

        while ($scoredVideos->count() < $limit && $attempts < $maxAttempts) {
            $attempts++;
            $batchSize = $limit * $batchMultiplier * $attempts;

            $query = Video::query()
                ->select([
                    'videos.*',
                    DB::raw('0 as feed_score'),
                ])
                ->where('videos.status', 2)
                ->where('videos.visibility', 1)
                ->where('videos.created_at', '>=', now()->subDays(self::MAX_AGE_DAYS))
                ->orderByDesc('videos.id')
                ->with([
                    'profile:id,username',
                    'hashtags:hashtags.id,hashtags.name',
                ]);

            // Apply user filters via join
            $query = $this->applyUserFilters($query, $profile->id);
            $query = $this->applyContentPreferences($query, $profile);

            if ($lastId !== null) {
                $query->where('videos.id', '<', $lastId);
            }

            $videos = $query->limit($batchSize)->get();

            if ($videos->isEmpty()) {
                break;
            }

            $lastId = $videos->last()->id;

            $unviewedVideos = app(ImpressionBloomFilterService::class)
                ->filterVideos($profile->id, $videos);

            $batchScored = $unviewedVideos->map(function ($video) use ($profile, $interests) {
                $score = $this->calculateVideoScore($video, $profile, $interests);
                $video->feed_score = $score;

                return $video;
            })->filter(fn ($v) => $v->feed_score >= self::MIN_SCORE_THRESHOLD);

            $scoredVideos = $scoredVideos->concat($batchScored);
        }

        return $scoredVideos
            ->sortByDesc('feed_score')
            ->take($limit)
            ->values();
    }

    private function getPopularVideos(
        Profile $profile,
        ?int $cursorId,
        int $limit,
        array $alreadyIncludedIds = []
    ): Collection {
        $query = Video::query()
            ->select([
                'videos.*',
                DB::raw('(
                    (videos.likes * 2) +
                    (videos.comments * 3) +
                    (videos.shares * 4) +
                    (videos.bookmarks * 3) +
                    (videos.views * 0.1)
                ) / (TIMESTAMPDIFF(HOUR, videos.created_at, NOW()) + 1) as feed_score'),
            ])
            ->where('videos.status', 2)
            ->where('videos.visibility', 1)
            ->where('videos.created_at', '>=', now()->subDays(self::MAX_AGE_DAYS))
            ->whereNotIn('videos.id', $alreadyIncludedIds)
            ->with([
                'profile:id,username',
                'hashtags:hashtags.id,hashtags.name',
            ]);

        // Apply user filters via join
        $query = $this->applyUserFilters($query, $profile->id);
        $query = $this->applyContentPreferences($query, $profile);

        if ($cursorId) {
            $query->where('videos.id', '<', $cursorId);
        }

        $videos = $query
            ->orderByDesc('feed_score')
            ->limit($limit)
            ->get();

        $videos = app(ImpressionBloomFilterService::class)->filterVideos($profile->id, $videos);

        return $videos;
    }

    private function getDiscoveryVideos(
        Profile $profile,
        array $alreadyIncludedIds,
        int $limit
    ): Collection {
        $query = Video::query()
            ->select([
                'videos.*',
                DB::raw('RAND() as feed_score'),
            ])
            ->where('videos.status', 2)
            ->where('videos.visibility', 1)
            ->where('videos.created_at', '>=', now()->subDays(self::MAX_AGE_DAYS))
            ->whereNotIn('videos.id', $alreadyIncludedIds)
            ->where('videos.likes', '>=', 2)
            ->with([
                'profile:id,username',
                'hashtags:hashtags.id,hashtags.name',
            ]);

        // Apply user filters via join
        $query = $this->applyUserFilters($query, $profile->id);
        $query = $this->applyContentPreferences($query, $profile);

        $videos = $query
            ->inRandomOrder()
            ->limit($limit)
            ->get();

        $videos = app(ImpressionBloomFilterService::class)->filterVideos($profile->id, $videos);

        return $videos;
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

    private function calculateVideoScore(Video $video, Profile $profile, Collection $interests): float
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
        $ageInDays = max(1, now()->diffInDays($video->created_at) + 1);

        $likeScore = min(1, $video->likes / 1000);
        $commentScore = min(1, $video->comments / 100);
        $shareScore = min(1, $video->shares / 50);
        $bookmarkScore = min(1, $video->bookmarks / 50);
        $viewScore = min(1, $video->views / 5000);

        $rawEngagement = (
            ($likeScore * 0.30) +
            ($commentScore * 0.20) +
            ($shareScore * 0.25) +
            ($bookmarkScore * 0.15) +
            ($viewScore * 0.10)
        );

        return $rawEngagement / log($ageInDays + 2);
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

    private function generateCursor(Collection $videos): ?string
    {
        if ($videos->isEmpty()) {
            return null;
        }

        $boundaryId = (int) $videos->min('id');

        return base64_encode('0|'.$boundaryId);
    }

    private function decodeCursor(string $cursor): array
    {
        try {
            $decoded = base64_decode($cursor, true);
            if (! $decoded || ! str_contains($decoded, '|')) {
                return [null, null];
            }

            [$score, $id] = explode('|', $decoded, 2);

            return [
                is_numeric($score) ? (float) $score : null,
                is_numeric($id) ? (int) $id : null,
            ];
        } catch (\Throwable $e) {
            return [null, null];
        }
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
        FeedFeedback::create([
            'profile_id' => $profileId,
            'video_id' => $videoId,
            'feedback_type' => $feedbackType,
        ]);

        $this->updateUserInterestsFromFeedback($profileId, $videoId, $feedbackType);
    }

    private function updateUserInterestsFromFeedback(
        int $profileId,
        int $videoId,
        string $feedbackType
    ): void {
        $video = Video::with(['hashtags'])->find($videoId);

        if (! $video) {
            return;
        }

        $weight = match ($feedbackType) {
            FeedFeedback::TYPE_LIKE => 2.0,
            FeedFeedback::TYPE_DISLIKE => -1.0,
            FeedFeedback::TYPE_NOT_INTERESTED => -0.5,
            FeedFeedback::TYPE_HIDE_CREATOR => -3.0,
            default => 0,
        };

        $this->updateInterest(
            $profileId,
            UserInterest::TYPE_CREATOR,
            (string) $video->profile_id,
            $weight
        );

        if ($video->hashtags->isNotEmpty()) {
            foreach ($video->hashtags as $hashtag) {
                $this->updateInterest(
                    $profileId,
                    UserInterest::TYPE_HASHTAG,
                    $hashtag->name,
                    $weight
                );
            }
        }

        if ($video->category) {
            $this->updateInterest(
                $profileId,
                UserInterest::TYPE_CATEGORY,
                $video->category,
                $weight
            );
        }
    }

    private function updateInterest(
        int $profileId,
        string $type,
        string $value,
        float $weight
    ): void {
        $interest = UserInterest::firstOrCreate(
            [
                'profile_id' => $profileId,
                'interest_type' => $type,
                'interest_value' => $value,
            ],
            [
                'score' => 0,
                'interaction_count' => 0,
            ]
        );

        if ($weight > 0) {
            $interest->recordInteraction($weight);
        } else {
            $interest->score = max(0, $interest->score + ($weight * 5));
            $interest->save();
        }
    }
}
