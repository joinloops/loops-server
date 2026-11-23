<?php

namespace App\Jobs;

use App\Models\FeedFeedback;
use App\Models\Profile;
use App\Models\UserInterest;
use App\Models\Video;
use App\Models\VideoBookmark;
use App\Models\VideoLike;
use App\Services\ForYouFeedService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class UpdateUserInterestsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private const WINDOW_DAYS = 90;

    private const HALF_LIFE_DAYS = 30;

    private const SCORE_SATURATION = 5;

    private const MIN_SCORE = 1;

    private const LIKE_WEIGHT = 1.0;

    private const BOOKMARK_WEIGHT = 1.5;

    private const MAX_LIKES = 500;

    private const MAX_BOOKMARKS = 300;

    private const MAX_FEEDBACK = 500;

    public function __construct(
        private int $profileId
    ) {}

    public function handle(): void
    {
        $profile = Profile::find($this->profileId);

        if (! $profile) {
            return;
        }

        $this->storeInterests($this->scoreInterests($this->collectEvents()));

        app(ForYouFeedService::class)->forgetUserInterests($this->profileId);
    }

    private function collectEvents(): array
    {
        $since = now()->subDays(self::WINDOW_DAYS);
        $events = [];

        $likes = VideoLike::where('profile_id', $this->profileId)
            ->where('created_at', '>=', $since)
            ->orderByDesc('created_at')
            ->limit(self::MAX_LIKES)
            ->get(['video_id', 'created_at']);

        foreach ($likes as $like) {
            $events[] = [
                'video_id' => (int) $like->video_id,
                'weight' => self::LIKE_WEIGHT,
                'at' => $like->created_at,
            ];
        }

        $bookmarks = VideoBookmark::where('profile_id', $this->profileId)
            ->where('created_at', '>=', $since)
            ->orderByDesc('created_at')
            ->limit(self::MAX_BOOKMARKS)
            ->get(['video_id', 'created_at']);

        foreach ($bookmarks as $bookmark) {
            $events[] = [
                'video_id' => (int) $bookmark->video_id,
                'weight' => self::BOOKMARK_WEIGHT,
                'at' => $bookmark->created_at,
            ];
        }

        $feedback = FeedFeedback::where('profile_id', $this->profileId)
            ->where('created_at', '>=', $since)
            ->orderByDesc('created_at')
            ->limit(self::MAX_FEEDBACK)
            ->get(['video_id', 'feedback_type', 'created_at']);

        foreach ($feedback as $entry) {
            $events[] = [
                'video_id' => (int) $entry->video_id,
                'weight' => match ($entry->feedback_type) {
                    FeedFeedback::TYPE_LIKE => 2.0,
                    FeedFeedback::TYPE_DISLIKE => -1.0,
                    FeedFeedback::TYPE_NOT_INTERESTED => -0.5,
                    FeedFeedback::TYPE_HIDE_CREATOR => -3.0,
                },
                'at' => $entry->created_at,
            ];
        }

        return $events;
    }

    private function scoreInterests(array $events): array
    {
        if (empty($events)) {
            return [];
        }

        $videos = Video::whereIn('id', array_unique(array_column($events, 'video_id')))
            ->with('hashtags:hashtags.id,hashtags.name')
            ->get(['id', 'profile_id', 'category'])
            ->keyBy('id');

        $totals = [];

        foreach ($events as $event) {
            $video = $videos->get($event['video_id']);

            if (! $video || ! $event['at']) {
                continue;
            }

            $ageInDays = max(0, $event['at']->diffInDays(now()));
            $weight = $event['weight'] * pow(0.5, $ageInDays / self::HALF_LIFE_DAYS);
            $targets = [];

            if ((int) $video->profile_id !== $this->profileId) {
                $targets[] = [UserInterest::TYPE_CREATOR, (string) $video->profile_id];
            }

            foreach ($video->hashtags as $hashtag) {
                $targets[] = [UserInterest::TYPE_HASHTAG, (string) $hashtag->name];
            }

            if ($video->category) {
                $targets[] = [UserInterest::TYPE_CATEGORY, (string) $video->category];
            }

            foreach ($targets as [$type, $value]) {
                $key = $this->interestKey($type, $value);

                $totals[$key] ??= [
                    'type' => $type,
                    'value' => $value,
                    'raw' => 0.0,
                    'count' => 0,
                    'last' => $event['at'],
                ];

                $totals[$key]['raw'] += $weight;
                $totals[$key]['count']++;

                if ($event['at']->gt($totals[$key]['last'])) {
                    $totals[$key]['last'] = $event['at'];
                }
            }
        }

        $interests = [];

        foreach ($totals as $key => $total) {
            $score = round(100 * (1 - exp(-max(0, $total['raw']) / self::SCORE_SATURATION)), 4);

            if ($score < self::MIN_SCORE) {
                continue;
            }

            $interests[$key] = [
                'profile_id' => $this->profileId,
                'interest_type' => $total['type'],
                'interest_value' => $total['value'],
                'score' => $score,
                'interaction_count' => $total['count'],
                'last_interaction_at' => $total['last'],
            ];
        }

        return $interests;
    }

    private function storeInterests(array $interests): void
    {
        $staleIds = UserInterest::where('profile_id', $this->profileId)
            ->get(['id', 'interest_type', 'interest_value'])
            ->reject(fn (UserInterest $interest) => isset(
                $interests[$this->interestKey($interest->interest_type, $interest->interest_value)]
            ))
            ->pluck('id');

        if ($staleIds->isNotEmpty()) {
            UserInterest::whereIn('id', $staleIds)->delete();
        }

        foreach (array_chunk(array_values($interests), 500) as $chunk) {
            UserInterest::upsert(
                $chunk,
                ['profile_id', 'interest_type', 'interest_value'],
                ['score', 'interaction_count', 'last_interaction_at']
            );
        }
    }

    private function interestKey(string $type, string $value): string
    {
        return $type.'|'.mb_strtolower($value);
    }
}
