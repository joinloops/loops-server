<?php

namespace App\Console\Commands;

use App\Jobs\Video\VideoCustomThumbnailJob;
use App\Jobs\Video\VideoOptimizeJob;
use App\Jobs\Video\VideoProcessingCompleteJob;
use App\Jobs\Video\VideoThumbnailJob;
use App\Models\Video;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class RecoverStuckVideos extends Command
{
    protected $signature = 'loops:videos:recover
        {--id=* : Target one or more video IDs}
        {--older-than=120 : Minimum time since the last update, in minutes}
        {--limit=50 : Maximum number of videos to inspect (1-1000)}
        {--run : Dispatch jobs; without this flag, only report candidates}';

    protected $description = 'Dry-run or safely requeue pending local videos with recoverable S3 media';

    public function handle(): int
    {
        $ids = (array) $this->option('id');
        foreach ($ids as $id) {
            if (! is_string($id) || ! ctype_digit($id)) {
                $this->error('--id must contain decimal video IDs.');

                return self::FAILURE;
            }
        }

        $olderThan = filter_var($this->option('older-than'), FILTER_VALIDATE_INT);
        $limit = filter_var($this->option('limit'), FILTER_VALIDATE_INT);
        if ($olderThan === false || $olderThan < 1 || $limit === false || $limit < 1 || $limit > 1000) {
            $this->error('--older-than must be positive and --limit must be between 1 and 1000.');

            return self::FAILURE;
        }

        $query = Video::query()
            ->where('is_local', true)
            ->where('status', 1)
            ->whereNull('scheduled_at')
            ->whereNotNull('vid');

        if ($ids !== []) {
            $query->whereIn('id', $ids);
        } else {
            $query->whereIn('processing_status', ['pending', 'processing', 'failed'])
                ->where('updated_at', '<=', now()->subMinutes($olderThan));
        }

        $run = (bool) $this->option('run');
        $disk = Storage::disk('s3');
        $found = 0;
        $queued = 0;

        foreach ($query->orderBy('id')->limit($limit)->get() as $video) {
            if ($video->processing_status === 'processing' && $video->updated_at &&
                $video->updated_at->gt(now()->subMinutes($olderThan))) {
                $this->warn("Skipping {$video->id}: processing is still recent");

                continue;
            }

            if (! $video->vid || str_contains($video->vid, '://')) {
                $this->warn("Skipping {$video->id}: no local video path");

                continue;
            }

            try {
                $optimizedReady = $video->vid_optimized &&
                    $disk->exists($video->vid_optimized) && $disk->size($video->vid_optimized) >= 1024;
                $sourceReady = $disk->exists($video->vid) && $disk->size($video->vid) >= 1024;

                if (! $optimizedReady && ! $sourceReady) {
                    $this->warn("Skipping {$video->id}: original and optimized objects are missing or empty");

                    continue;
                }

                $thumbnailReady = $video->has_thumb && $video->thumbnail_path && $disk->exists($video->thumbnail_path);
                $customThumbnailReady = $video->thumbnail_path && $disk->exists($video->thumbnail_path);
            } catch (\Throwable $e) {
                Log::warning('Video recovery storage lookup failed', [
                    'video_id' => $video->id,
                    'error' => $e->getMessage(),
                ]);
                $this->warn("Skipping {$video->id}: storage lookup failed");

                continue;
            }

            $found++;
            if (! $run) {
                $this->line("Would requeue {$video->id} [{$video->processing_status}]".
                    ($optimizedReady ? ' (optimized object exists)' : ' (transcode original)'));

                continue;
            }

            $ttl = max(3600, (int) config('loops.media.transcode.job_timeout', 900) + 300);
            $lock = Cache::lock('loops:video-recovery:'.$video->id, $ttl);
            if (! $lock->get()) {
                $this->warn("Skipping {$video->id}: recovery has already been queued");

                continue;
            }

            try {
                $video->processing_status = 'processing';
                $video->processing_error = null;
                $video->processing_failed_at = null;
                $video->saveQuietly();

                VideoOptimizeJob::withChain([
                    new VideoProcessingCompleteJob($video),
                ])->dispatch($video);

                $queued++;

                if (! $thumbnailReady) {
                    try {
                        if ($customThumbnailReady) {
                            VideoCustomThumbnailJob::dispatch($video);
                        } else {
                            VideoThumbnailJob::dispatch($video);
                        }
                    } catch (\Throwable $thumbnailError) {
                        Log::warning('Recovery thumbnail dispatch failed', [
                            'video_id' => $video->id,
                            'error' => $thumbnailError->getMessage(),
                        ]);
                        $this->warn("Video {$video->id} requeued without thumbnail generation");
                    }
                }

                $this->info("Requeued {$video->id}");
            } catch (\Throwable $e) {
                $lock->release();
                $video->refresh();
                if ($video->processing_status !== 'completed' && ! $video->isLive()) {
                    $video->processing_status = 'failed';
                    $video->processing_error = 'Recovery dispatch failed: '.\Illuminate\Support\Str::limit($e->getMessage(), 900);
                    $video->processing_failed_at = now();
                    $video->saveQuietly();
                }
                $this->error("Failed to requeue {$video->id}: {$e->getMessage()}");
                Log::error('Video recovery dispatch failed', [
                    'video_id' => $video->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->info($run
            ? "Queued {$queued} of {$found} recoverable videos."
            : "Found {$found} recoverable videos (dry-run). Add --run to dispatch.");

        return self::SUCCESS;
    }
}
