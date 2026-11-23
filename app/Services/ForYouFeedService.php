<?php

namespace App\Services;

use App\Http\Resources\VideoResource;
use App\Jobs\UpdateUserInterestsJob;
use App\Models\FeedFeedback;
use App\Models\Profile;
use App\Models\UserInterest;
use App\Models\VideoBookmark;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class ForYouFeedService
{
    private const DEFAULT_LIMIT = 20;

    private const MAX_LIMIT = 50;

    private const REFILL_PAGES = 3;

    private const MAX_FILL_ROUNDS = 6;

    private const SESSION_TTL_MINUTES = 120;

    private const SESSION_MAX_ITEMS = 400;

    private const SESSION_TAIL_SIZE = 20;

    private const INTEREST_LIMIT = 100;

    private const INTEREST_REFRESH_MINUTES = 30;

    private const FEEDBACK_TTL_MINUTES = 30;

    private const FEEDBACK_MAX_VIDEOS = 1000;

    private const FEEDBACK_MAX_CREATORS = 500;

    public function __construct(
        private ForYouCandidateService $candidates,
        private ForYouRankingService $ranking,
        private ImpressionBloomFilterService $bloom
    ) {}

    public function getFeed(
        Profile $profile,
        ?string $cursor = null,
        int $limit = self::DEFAULT_LIMIT
    ): array {
        $limit = max(1, min($limit, self::MAX_LIMIT));
        $profileId = (int) $profile->id;
        $fresh = $cursor === null || $cursor === '';
        $session = $this->getSession($profileId, $fresh);

        if ($fresh) {
            $this->refreshInterests($profileId);
        }

        $context = $this->getContext($profileId);
        $offset = $fresh ? 0 : ($this->decodeCursor($cursor) ?? $session['served']);
        $index = max(0, min($offset - $session['base'], count($session['items'])));
        $videos = collect();

        for ($round = 0; $round < self::MAX_FILL_ROUNDS && $videos->count() < $limit; $round++) {
            $needed = $limit - $videos->count();

            if (count($session['items']) - $index < $needed && ! $session['dry']) {
                $this->refill($profile, $session, $context, $limit);
            }

            $ids = array_slice($session['items'], $index, $needed);

            if (empty($ids)) {
                break;
            }

            $index += count($ids);
            $videos = $videos->concat($this->loadVideos($profile, $context, $ids));
        }

        if ($index >= count($session['items']) && ! $session['dry']) {
            $this->refill($profile, $session, $context, $limit);
        }

        $this->bloom->markServed($profileId, $videos->pluck('id')->all());

        $hasMore = $index < count($session['items']);
        $session['served'] = max($session['served'], $session['base'] + $index);

        $overflow = min($index, count($session['items']) - self::SESSION_MAX_ITEMS);
        if ($overflow > 0) {
            $session['items'] = array_slice($session['items'], $overflow);
            $session['base'] += $overflow;
            $index -= $overflow;
        }

        $this->putSession($profileId, $session);

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
                'next_cursor' => $hasMore ? $this->encodeCursor($session['base'] + $index) : null,
                'prev_cursor' => null,
                'has_more' => $hasMore,
            ],
        ];
    }

    private function refill(Profile $profile, array &$session, array $context, int $limit): void
    {
        $count = $limit * self::REFILL_PAGES;
        $excluded = array_fill_keys(array_merge($session['items'], $context['excluded_videos']), true);
        $ids = $this->rankCandidates($profile, $session, $context, $excluded, $count);

        if (
            count($ids) < $count
            && $session['walk']['exhausted']
            && $session['walk']['position'] !== null
            && $session['walk']['picks'] > 0
        ) {
            $session['walk'] = $this->newWalk();
            $ids = array_merge($ids, $this->rankCandidates(
                $profile,
                $session,
                $context,
                $excluded + array_fill_keys($ids, true),
                $count - count($ids)
            ));
        }

        if (empty($ids) && $this->recycleEnabled()) {
            $ids = $this->candidates->recycled($profile, $context['hidden_creators'], $excluded, $count);
        }

        if (empty($ids)) {
            $session['dry'] = true;

            return;
        }

        $session['items'] = array_merge($session['items'], $ids);
    }

    private function rankCandidates(
        Profile $profile,
        array &$session,
        array $context,
        array $excluded,
        int $count
    ): array {
        $gathered = $this->candidates->gather($profile, $context, $excluded, $session['walk']);
        $rows = $this->candidates->rows(
            $profile,
            $context['hidden_creators'],
            $gathered['ids'],
            array_map('strval', array_keys($context['hashtags']))
        );
        $ids = $this->ranking->order(
            $rows,
            $gathered['lanes'],
            $gathered['served'],
            $context,
            $session['tail'],
            $count
        );

        $picked = array_fill_keys($ids, true);
        $carry = [];
        $walkPicks = 0;

        foreach ($gathered['walk']['ids'] as $id) {
            if (! isset($rows[$id])) {
                continue;
            }

            if (isset($picked[$id])) {
                $walkPicks++;

                continue;
            }

            $carry[$id] = $rows[$id]['creator'];
        }

        $session['walk'] = [
            'position' => $gathered['walk']['position'],
            'exhausted' => $gathered['walk']['exhausted'],
            'carry' => $carry,
            'picks' => $session['walk']['picks'] + $walkPicks,
        ];

        foreach ($ids as $id) {
            $session['tail'][] = $rows[$id]['creator'];
        }
        $session['tail'] = array_slice($session['tail'], -self::SESSION_TAIL_SIZE);

        return $ids;
    }

    private function loadVideos(Profile $profile, array $context, array $ids): Collection
    {
        $excluded = array_fill_keys($context['excluded_videos'], true);
        $ids = array_values(array_filter($ids, fn ($id) => ! isset($excluded[$id])));

        if (empty($ids)) {
            return collect();
        }

        $videos = $this->candidates->visibleVideos($profile, $context['hidden_creators'])
            ->whereIn('videos.id', $ids)
            ->select('videos.*')
            ->with([
                'profile:id,username',
                'hashtags:hashtags.id,hashtags.name',
            ])
            ->get()
            ->keyBy('id');

        $bookmarked = VideoBookmark::where('profile_id', $profile->id)
            ->whereIn('video_id', $ids)
            ->pluck('video_id')
            ->flip();

        $ordered = collect();

        foreach ($ids as $id) {
            $video = $videos->get($id);

            if ($video) {
                $video->setAttribute('is_bookmarked', $bookmarked->has($id));
                $ordered->push($video);
            }
        }

        return $ordered;
    }

    private function getContext(int $profileId): array
    {
        $interests = $this->getUserInterests($profileId);
        $feedback = $this->getFeedbackFilters($profileId);

        return [
            'creators' => $this->interestScores($interests, UserInterest::TYPE_CREATOR),
            'hashtags' => $this->interestScores($interests, UserInterest::TYPE_HASHTAG),
            'following' => array_fill_keys($this->candidates->followingSet($profileId), true),
            'hidden_creators' => $feedback['creators'],
            'excluded_videos' => $feedback['videos'],
        ];
    }

    private function interestScores(Collection $interests, string $type): array
    {
        return $interests
            ->where('interest_type', $type)
            ->mapWithKeys(fn ($interest) => [(string) $interest->interest_value => (float) $interest->score])
            ->all();
    }

    private function getUserInterests(int $profileId): Collection
    {
        return Cache::remember(
            "fyf:user_interests:{$profileId}",
            now()->addMinutes(30),
            fn () => UserInterest::where('profile_id', $profileId)
                ->where('score', '>', 0)
                ->orderByDesc('score')
                ->limit(self::INTEREST_LIMIT)
                ->get()
        );
    }

    public function forgetUserInterests(int $profileId): void
    {
        Cache::forget("fyf:user_interests:{$profileId}");
    }

    private function refreshInterests(int $profileId): void
    {
        $due = Cache::add(
            "fyf:interests_refresh:{$profileId}",
            1,
            now()->addMinutes(self::INTEREST_REFRESH_MINUTES)
        );

        if ($due) {
            UpdateUserInterestsJob::dispatch($profileId);
        }
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

        if (! is_array($session) || ! isset($session['items'], $session['walk'])) {
            return [
                'items' => [],
                'base' => 0,
                'served' => 0,
                'tail' => [],
                'dry' => false,
                'walk' => $this->newWalk(),
            ];
        }

        return $session;
    }

    private function putSession(int $profileId, array $session): void
    {
        Cache::put(
            "fyf:session:{$profileId}",
            $session,
            now()->addMinutes(self::SESSION_TTL_MINUTES)
        );
    }

    private function newWalk(): array
    {
        return ['position' => null, 'exhausted' => false, 'carry' => [], 'picks' => 0];
    }

    private function recycleEnabled(): bool
    {
        return (bool) config('loops.feed.fyf.recycle.enabled', true);
    }

    private function encodeCursor(int $offset): string
    {
        return base64_encode('q|'.$offset);
    }

    private function decodeCursor(string $cursor): ?int
    {
        $decoded = base64_decode($cursor, true);

        if (! $decoded || ! str_starts_with($decoded, 'q|')) {
            return null;
        }

        $offset = substr($decoded, 2);

        return ctype_digit($offset) ? (int) $offset : null;
    }

    public function recordImpression(
        int $profileId,
        int $videoId,
        int $watchDuration = 0,
        bool $completed = false
    ): void {
        $watchDuration = max(0, min($watchDuration, 65535));

        $previous = DB::table('feed_impressions')
            ->where('profile_id', $profileId)
            ->where('video_id', $videoId)
            ->value('completed');

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

        $isNew = $previous === null;
        $newlyCompleted = $completed && ! $previous;

        if ($isNew || $newlyCompleted) {
            DB::statement(
                '
                    INSERT INTO video_feed_stats (
                        video_id,
                        impressions,
                        completions,
                        updated_at
                    )
                    VALUES (?, ?, ?, NOW())
                    AS new
                    ON DUPLICATE KEY UPDATE
                        impressions = video_feed_stats.impressions + new.impressions,
                        completions = video_feed_stats.completions + new.completions,
                        updated_at = new.updated_at
                    ',
                [
                    $videoId,
                    $isNew ? 1 : 0,
                    $newlyCompleted ? 1 : 0,
                ]
            );
        }

        $this->bloom->add($profileId, $videoId);
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
