<?php

namespace App\Jobs;

use App\Models\FeedFeedback;
use App\Models\Profile;
use App\Models\UserInterest;
use App\Services\ForYouFeedService;
use DateTimeInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class UpdateUserInterestsJob implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $uniqueFor = 900;

    public int $maxExceptions = 3;

    public array $backoff = [5, 15, 30];

    private const TRANSACTION_ATTEMPTS = 3;

    private const WINDOW_DAYS = 90;

    private const HALF_LIFE_DAYS = 30;

    private const SCORE_SATURATION = 5;

    private const MIN_SCORE = 1;

    private const LIKE_WEIGHT = 1.0;

    private const BOOKMARK_WEIGHT = 1.5;

    private const REPOST_WEIGHT = 1.5;

    private const VIEW_WEIGHT = 0.5;

    private const VIEW_WINDOW_DAYS = 30;

    private const MIN_VIEWS = 20;

    private const MAX_VIEWS = 1000;

    private const MAX_LIKES = 500;

    private const MAX_BOOKMARKS = 300;

    private const MAX_REPOSTS = 300;

    private const MAX_FEEDBACK = 500;

    private const CHUNK_SIZE = 500;

    public function __construct(
        private int $profileId
    ) {}

    public function uniqueId(): string
    {
        return (string) $this->profileId;
    }

    public function middleware(): array
    {
        return [
            (new WithoutOverlapping((string) $this->profileId))
                ->releaseAfter(10)
                ->expireAfter(120),
        ];
    }

    public function retryUntil(): DateTimeInterface
    {
        return now()->addMinutes(30);
    }

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

        $events = array_merge(
            $this->tableEvents('video_likes', $since, self::MAX_LIKES, self::LIKE_WEIGHT),
            $this->tableEvents('video_bookmarks', $since, self::MAX_BOOKMARKS, self::BOOKMARK_WEIGHT),
            $this->tableEvents('video_reposts', $since, self::MAX_REPOSTS, self::REPOST_WEIGHT),
            $this->viewEvents()
        );

        $feedback = DB::table('feed_feedback')
            ->where('profile_id', $this->profileId)
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
                    default => 0.0,
                },
                'at' => strtotime($entry->created_at),
            ];
        }

        return $events;
    }

    private function tableEvents(string $table, Carbon $since, int $limit, float $weight): array
    {
        return DB::table($table)
            ->where('profile_id', $this->profileId)
            ->where('created_at', '>=', $since)
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get(['video_id', 'created_at'])
            ->map(fn ($row) => [
                'video_id' => (int) $row->video_id,
                'weight' => $weight,
                'at' => strtotime($row->created_at),
            ])
            ->all();
    }

    private function viewEvents(): array
    {
        $views = DB::table('feed_impressions')
            ->where('profile_id', $this->profileId)
            ->where('viewed_at', '>=', now()->subDays(self::VIEW_WINDOW_DAYS))
            ->orderByDesc('viewed_at')
            ->limit(self::MAX_VIEWS)
            ->get(['video_id', 'viewed_at', 'completed']);

        if ($views->count() < self::MIN_VIEWS) {
            return [];
        }

        $completionRate = max(0.05, min(0.95, $views->avg('completed')));

        return $views
            ->map(fn ($view) => [
                'video_id' => (int) $view->video_id,
                'weight' => self::VIEW_WEIGHT * ((int) $view->completed - $completionRate),
                'at' => strtotime($view->viewed_at),
            ])
            ->all();
    }

    private function scoreInterests(array $events): array
    {
        if (empty($events)) {
            return [];
        }

        $videos = [];
        $hashtags = [];

        foreach (array_chunk(array_unique(array_column($events, 'video_id')), self::CHUNK_SIZE) as $chunk) {
            foreach (DB::table('videos')->whereIn('id', $chunk)->get(['id', 'profile_id', 'category']) as $video) {
                $videos[(int) $video->id] = $video;
            }

            $tagged = DB::table('video_hashtags')
                ->join('hashtags', 'hashtags.id', '=', 'video_hashtags.hashtag_id')
                ->whereIn('video_hashtags.video_id', $chunk)
                ->get(['video_hashtags.video_id', 'hashtags.name']);

            foreach ($tagged as $tag) {
                $hashtags[(int) $tag->video_id][] = (string) $tag->name;
            }
        }

        $now = time();
        $totals = [];

        foreach ($events as $event) {
            $video = $videos[$event['video_id']] ?? null;

            if (! $video || ! $event['at']) {
                continue;
            }

            $ageInDays = max(0, $now - $event['at']) / 86400;
            $weight = $event['weight'] * pow(0.5, $ageInDays / self::HALF_LIFE_DAYS);
            $targets = [];

            if ((int) $video->profile_id !== $this->profileId) {
                $targets[] = [UserInterest::TYPE_CREATOR, (string) $video->profile_id];
            }

            foreach ($hashtags[$event['video_id']] ?? [] as $hashtag) {
                $targets[] = [UserInterest::TYPE_HASHTAG, $hashtag];
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
                $totals[$key]['last'] = max($totals[$key]['last'], $event['at']);
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
                'last_interaction_at' => date('Y-m-d H:i:s', $total['last']),
            ];
        }

        return $interests;
    }

    private function storeInterests(array $interests): void
    {
        DB::transaction(function () use ($interests) {
            $staleIds = [];
            $updates = [];

            $existing = UserInterest::where('profile_id', $this->profileId)
                ->get(['id', 'interest_type', 'interest_value']);

            foreach ($existing as $interest) {
                $key = $this->interestKey($interest->interest_type, $interest->interest_value);

                if (! isset($interests[$key])) {
                    $staleIds[] = (int) $interest->id;

                    continue;
                }

                $updates[(int) $interest->id] = $interests[$key];
                unset($interests[$key]);
            }

            foreach (array_chunk($staleIds, self::CHUNK_SIZE) as $chunk) {
                UserInterest::whereIn('id', $chunk)->delete();
            }

            $this->updateExistingInterests($updates);

            $inserts = array_values($interests);

            usort($inserts, fn (array $a, array $b) => strcmp($a['interest_type'], $b['interest_type'])
                ?: strcmp(mb_strtolower($a['interest_value']), mb_strtolower($b['interest_value'])));

            foreach (array_chunk($inserts, self::CHUNK_SIZE) as $chunk) {
                UserInterest::upsert(
                    $chunk,
                    ['profile_id', 'interest_type', 'interest_value'],
                    ['score', 'interaction_count', 'last_interaction_at']
                );
            }
        }, self::TRANSACTION_ATTEMPTS);
    }

    private function updateExistingInterests(array $rows): void
    {
        if (empty($rows)) {
            return;
        }

        ksort($rows);

        $grammar = DB::getQueryGrammar();
        $table = $grammar->wrapTable((new UserInterest)->getTable());
        $idColumn = $grammar->wrap('id');
        $columns = ['score', 'interaction_count', 'last_interaction_at'];
        $now = now();

        foreach (array_chunk($rows, self::CHUNK_SIZE, true) as $chunk) {
            $sets = [];
            $bindings = [];

            foreach ($columns as $column) {
                $sets[] = $grammar->wrap($column).' = case '.$idColumn.' '
                    .implode(' ', array_fill(0, count($chunk), 'when ? then ?')).' end';

                foreach ($chunk as $id => $row) {
                    $bindings[] = $id;
                    $bindings[] = $row[$column];
                }
            }

            $sets[] = $grammar->wrap('updated_at').' = ?';
            $bindings[] = $now;

            $ids = array_keys($chunk);

            DB::update(
                'update '.$table.' set '.implode(', ', $sets)
                .' where '.$idColumn.' in ('.implode(', ', array_fill(0, count($ids), '?')).')',
                array_merge($bindings, $ids)
            );
        }
    }

    private function interestKey(string $type, string $value): string
    {
        return $type.'|'.mb_strtolower($value);
    }
}
