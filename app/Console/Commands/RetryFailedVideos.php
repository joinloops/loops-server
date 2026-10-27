<?php

namespace App\Console\Commands;

use App\Jobs\Video\VideoOptimizeJob;
use App\Jobs\Video\VideoProcessingCompleteJob;
use App\Jobs\Video\VideoThumbnailJob;
use App\Models\Video;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

class RetryFailedVideos extends Command
{
    protected $signature = 'videos:retry-failed
        {--id= : Specific video ID to retry}
        {--stuck= : Also retry uploads stuck in pending/processing for more than this many hours}
        {--dry-run : List the videos that would be retried without dispatching anything}';

    protected $description = 'Retry processing for failed (and optionally stuck) local videos';

    public function handle()
    {
        $stuckHours = $this->option('stuck') !== null ? max(1, (int) $this->option('stuck')) : null;

        $query = Video::where('is_local', true)
            ->whereNull('scheduled_at')
            ->whereNotNull('vid')
            ->where(function (Builder $q) use ($stuckHours) {
                $q->where('processing_status', 'failed');

                if ($stuckHours !== null) {
                    $q->orWhere(function (Builder $q) use ($stuckHours) {
                        $q->whereIn('processing_status', ['pending', 'processing'])
                            ->where('updated_at', '<', now()->subHours($stuckHours));
                    });
                }
            });

        if ($this->option('id')) {
            $query->where('id', $this->option('id'));
        }

        $videos = $query->get();

        if ($videos->isEmpty()) {
            $this->info('No failed videos found.');

            return self::SUCCESS;
        }

        $this->info("Found {$videos->count()} video(s) to retry.");

        foreach ($videos as $video) {
            if ($this->option('dry-run')) {
                $this->line("  #{$video->id} [{$video->processing_status}] ".($video->processing_error ?? ''));

                continue;
            }

            $video->processing_status = 'processing';
            $video->processing_error = null;
            $video->processing_failed_at = null;
            $video->save();

            if (! $video->has_thumb) {
                VideoThumbnailJob::dispatch($video);
            }

            VideoOptimizeJob::withChain([
                new VideoProcessingCompleteJob($video),
            ])->dispatch($video);

            $this->info("Retrying video ID: {$video->id}");
        }

        if (! $this->option('dry-run')) {
            $this->info('Retry jobs dispatched.');
        }

        return self::SUCCESS;
    }
}
