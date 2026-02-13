<?php

namespace App\Console\Commands;

use App\Jobs\Video\ProcessVideoAudioJob;
use App\Models\Video;
use Illuminate\Console\Command;

class ProcessVideoAudioCommand extends Command
{
    protected $signature = 'videos:process-audio 
                            {--batch=100 : Number of videos to process per batch}
                            {--delay=2 : Delay in seconds between job dispatches}
                            {--video-id= : Process specific video ID}
                            {--limit= : Limit total number of videos to process}
                            {--dry-run : Show what would be processed without actually processing}';

    protected $description = 'Process videos to extract audio fingerprints for unprocessed videos';

    public function handle()
    {
        $batchSize = (int) $this->option('batch');
        $delay = (int) $this->option('delay');
        $videoId = $this->option('video-id');
        $limit = $this->option('limit') ? (int) $this->option('limit') : null;
        $dryRun = (bool) $this->option('dry-run');

        $baseQuery = Video::query()
            ->whereNull('audio_processed_at')
            ->where('is_local', true)
            ->where('status', 2)
            ->orderBy('id', 'asc');

        if ($videoId) {
            $baseQuery->whereKey($videoId);
        }

        $totalCount = $limit
            ? min($baseQuery->count(), $limit)
            : $baseQuery->count();

        if ($totalCount === 0) {
            $this->info('No unprocessed videos found.');

            return 0;
        }

        if ($dryRun) {
            $this->info("Dry run mode - would process {$totalCount} videos");

            $sample = (clone $baseQuery)
                ->limit(min(10, $totalCount))
                ->get(['id', 'created_at', 'profile_id']);

            $this->table(
                ['ID', 'Created At', 'Profile ID'],
                $sample->map(fn ($v) => [$v->id, $v->created_at, $v->profile_id])
            );

            if ($totalCount > 10) {
                $this->info('... and '.($totalCount - 10).' more videos');
            }

            return 0;
        }

        $this->info("Found {$totalCount} unprocessed videos");

        $bar = $this->output->createProgressBar($totalCount);
        $bar->start();

        $dispatched = 0;
        $failed = 0;

        (clone $baseQuery)
            ->select('id')
            ->chunkById($batchSize, function ($videos) use ($delay, $limit, $bar, &$dispatched, &$failed) {
                foreach ($videos as $video) {
                    if ($limit !== null && $dispatched >= $limit) {
                        return false;
                    }

                    try {
                        $job = ProcessVideoAudioJob::dispatch($video->id)
                            ->onQueue('audio-processing');

                        if ($delay > 0) {
                            $job->delay(now()->addSeconds($dispatched * $delay));
                        }

                        $dispatched++;
                        $bar->advance();
                    } catch (\Throwable $e) {
                        $failed++;
                        $this->error("\nFailed to dispatch job for video {$video->id}: {$e->getMessage()}");
                    }
                }
            });

        $bar->finish();
        $this->newLine(2);

        $this->info("✓ Dispatched {$dispatched} audio processing jobs");

        if ($failed > 0) {
            $this->warn("✗ Failed to dispatch {$failed} jobs");
        }

        $this->info("Jobs queued on 'audio-processing' (Horizon supervisor: audio-processor)");

        return 0;
    }
}
