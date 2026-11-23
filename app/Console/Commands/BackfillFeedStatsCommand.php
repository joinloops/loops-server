<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BackfillFeedStatsCommand extends Command
{
    protected $signature = 'feed:backfill-stats {--chunk=500 : Videos to aggregate per batch}';

    protected $description = 'Rebuild per-video For You feed stats from recorded impressions';

    public function handle(): int
    {
        $chunk = max(1, (int) $this->option('chunk'));
        $lastId = 0;
        $videos = 0;

        while (true) {
            $ids = DB::table('videos')
                ->where('id', '>', $lastId)
                ->orderBy('id')
                ->limit($chunk)
                ->pluck('id');

            if ($ids->isEmpty()) {
                break;
            }

            $lastId = $ids->last();

            $rows = DB::table('feed_impressions')
                ->whereIn('video_id', $ids)
                ->groupBy('video_id')
                ->selectRaw('video_id, COUNT(*) as impressions, COALESCE(SUM(completed), 0) as completions')
                ->get()
                ->map(fn ($row) => [
                    'video_id' => $row->video_id,
                    'impressions' => (int) $row->impressions,
                    'completions' => (int) $row->completions,
                    'updated_at' => now(),
                ])
                ->all();

            if (! empty($rows)) {
                DB::table('video_feed_stats')->upsert(
                    $rows,
                    ['video_id'],
                    ['impressions', 'completions', 'updated_at']
                );
            }

            $videos += count($rows);
            $this->line("Processed up to video {$lastId}");
        }

        $this->info("Feed stats rebuilt for {$videos} videos.");

        return self::SUCCESS;
    }
}
