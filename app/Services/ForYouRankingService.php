<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class ForYouRankingService
{
    private const FOLLOW_AFFINITY = 0.5;

    private const CREATOR_AFFINITY_SHARE = 0.65;

    private const QUALITY_PRIOR_WEIGHT = 20;

    private const REFERENCE_DAYS = 60;

    private const REFERENCE_PERCENTILE = 0.05;

    private const REFERENCE_MIN_VIDEOS = 20;

    private const REFERENCE_MIN_IMPRESSIONS = 100;

    private const DEFAULT_ENGAGEMENT_REFERENCE = 50;

    private const DEFAULT_COMPLETION_RATE = 0.4;

    private const CREATOR_WINDOW = 20;

    private const CREATOR_SPACING = 3;

    public function order(
        array $rows,
        array $lanes,
        array $served,
        array $context,
        array $tail,
        int $count
    ): array {
        if (empty($rows) || $count <= 0) {
            return [];
        }

        [$personal, $global] = $this->score($rows, $context);

        arsort($personal);
        arsort($global);

        $queues = [
            'ranked' => array_keys(array_diff_key($personal, $served)),
            'trending' => array_keys(array_diff_key(array_intersect_key($global, $lanes['trending']), $served)),
            'explore' => array_keys(array_diff_key(array_intersect_key($lanes['explore'], $rows), $served)),
            'served' => array_keys(array_intersect_key($personal, $served)),
        ];

        $mix = $this->mix();
        $cap = max(1, (int) config('loops.feed.fyf.creator_cap', 2));
        $recent = array_slice($tail, -self::CREATOR_WINDOW);
        $carryover = ['trending' => 0.5, 'explore' => 0.5];
        $picked = [];
        $order = [];

        while (count($order) < $count) {
            $lane = 'ranked';
            $carryover['trending'] += $mix['trending'];
            $carryover['explore'] += $mix['explore'];

            if ($carryover['explore'] >= 1) {
                $carryover['explore'] -= 1;
                $lane = 'explore';
            } elseif ($carryover['trending'] >= 1) {
                $carryover['trending'] -= 1;
                $lane = 'trending';
            }

            $id = $this->pick($queues, $lane, $rows, $picked, $recent, $cap);

            if ($id === null) {
                break;
            }

            $picked[$id] = true;
            $order[] = $id;
            $recent[] = $rows[$id]['creator'];
            $recent = array_slice($recent, -self::CREATOR_WINDOW);
        }

        return $order;
    }

    private function pick(array &$queues, string $lane, array $rows, array $picked, array $recent, int $cap): ?int
    {
        $counts = array_count_values($recent);
        $spaced = array_flip(array_slice($recent, -self::CREATOR_SPACING));
        $tiers = [array_unique([$lane, 'ranked', 'trending', 'explore']), ['served']];

        foreach ($queues as $name => $queue) {
            $queues[$name] = array_values(array_filter($queue, fn ($id) => ! isset($picked[$id])));
        }

        foreach ($tiers as $lanes) {
            foreach ([2, 1, 0] as $strictness) {
                foreach ($lanes as $name) {
                    foreach ($queues[$name] as $id) {
                        $creator = $rows[$id]['creator'];

                        if ($strictness >= 1 && ($counts[$creator] ?? 0) >= $cap) {
                            continue;
                        }

                        if ($strictness >= 2 && isset($spaced[$creator])) {
                            continue;
                        }

                        return $id;
                    }
                }
            }
        }

        return null;
    }

    private function score(array $rows, array $context): array
    {
        $reference = $this->reference();
        $weights = $this->weights();
        $halfLife = max(0.1, (float) config('loops.feed.fyf.freshness_half_life_days', 7));
        $globalWeight = $weights['engagement'] + $weights['quality'] + $weights['freshness'];
        $personalWeight = $globalWeight + $weights['affinity'];
        $now = time();
        $personal = [];
        $global = [];

        foreach ($rows as $id => $row) {
            $engagement = min(1, log1p(max(0, $row['interactions'])) / log1p($reference['engagement']));
            $quality = ($row['completions'] + ($reference['completion'] * self::QUALITY_PRIOR_WEIGHT))
                / ($row['impressions'] + self::QUALITY_PRIOR_WEIGHT);
            $freshness = pow(0.5, max(0, $now - $row['created_at']) / 86400 / $halfLife);

            $base = ($weights['engagement'] * $engagement)
                + ($weights['quality'] * min(1, $quality))
                + ($weights['freshness'] * $freshness);

            $trust = 0.5 + (max(0, min(10, $row['trust'])) / 10);

            $global[$id] = $globalWeight > 0 ? ($base / $globalWeight) * $trust : 0;
            $personal[$id] = $personalWeight > 0
                ? (($base + ($weights['affinity'] * $this->affinity($row, $context))) / $personalWeight) * $trust
                : 0;
        }

        return [$personal, $global];
    }

    private function affinity(array $row, array $context): float
    {
        $creator = ($context['creators'][$row['creator']] ?? 0) / 100;

        if (isset($context['following'][$row['creator']])) {
            $creator = max($creator, self::FOLLOW_AFFINITY);
        }

        $hashtag = 0;
        foreach ($row['tags'] as $tag) {
            $hashtag = max($hashtag, ($context['hashtags'][$tag] ?? 0) / 100);
        }

        return min(1, (self::CREATOR_AFFINITY_SHARE * $creator) + ((1 - self::CREATOR_AFFINITY_SHARE) * $hashtag));
    }

    private function weights(): array
    {
        return [
            'affinity' => max(0, (float) config('loops.feed.fyf.weights.affinity', 0.35)),
            'engagement' => max(0, (float) config('loops.feed.fyf.weights.engagement', 0.25)),
            'quality' => max(0, (float) config('loops.feed.fyf.weights.quality', 0.20)),
            'freshness' => max(0, (float) config('loops.feed.fyf.weights.freshness', 0.20)),
        ];
    }

    private function mix(): array
    {
        return [
            'trending' => max(0, min(1, (float) config('loops.feed.fyf.mix.trending', 0.2))),
            'explore' => max(0, min(1, (float) config('loops.feed.fyf.mix.explore', 0.1))),
        ];
    }

    private function reference(): array
    {
        return Cache::flexible('fyf:reference', [1800, 7200], function () {
            $recent = DB::table('videos')
                ->where('status', 2)
                ->where('visibility', 1)
                ->where('id', '>', (int) SnowflakeService::byDate(now()->subDays(self::REFERENCE_DAYS)));

            $count = (clone $recent)->count();
            $engagement = self::DEFAULT_ENGAGEMENT_REFERENCE;

            if ($count >= self::REFERENCE_MIN_VIDEOS) {
                $engagement = (float) $recent
                    ->selectRaw('(likes * 2) + (comments * 3) + (shares * 4) + (bookmarks * 3) as interactions')
                    ->orderByDesc('interactions')
                    ->offset((int) floor($count * self::REFERENCE_PERCENTILE))
                    ->limit(1)
                    ->value('interactions');
            }

            $stats = DB::table('video_feed_stats')
                ->selectRaw('SUM(impressions) as impressions, SUM(completions) as completions')
                ->first();

            $impressions = (int) ($stats->impressions ?? 0);
            $completion = $impressions >= self::REFERENCE_MIN_IMPRESSIONS
                ? ((int) $stats->completions) / $impressions
                : self::DEFAULT_COMPLETION_RATE;

            return [
                'engagement' => max(10, $engagement),
                'completion' => max(0.05, min(0.95, $completion)),
            ];
        });
    }
}
