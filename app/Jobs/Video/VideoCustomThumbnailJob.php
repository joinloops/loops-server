<?php

namespace App\Jobs\Video;

use App\Models\Video;
use App\Services\VideoService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Format;
use Intervention\Image\Laravel\Facades\Image;

class VideoCustomThumbnailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;

    public $backoff = 10;

    public $timeout = 120;

    public function __construct(public Video $video)
    {
        $this->video = $video->withoutRelations();
        $this->onConnection('redis-video');
        $this->onQueue('video-processing');
    }

    public function handle(): void
    {
        if (! $this->video->thumbnail_path) {
            return;
        }

        $disk = Storage::disk('s3');
        $tempPath = null;

        try {
            $originalPath = $this->video->thumbnail_path;
            $contents = $disk->get($originalPath);

            if (! $contents) {
                throw new \Exception('Failed to read thumbnail from S3');
            }

            $image = Image::decodeBinary($contents);
            $image->cover(720, 1280);

            $width = $image->width();
            $height = $image->height();

            $tempPath = sys_get_temp_dir().'/'.uniqid($this->video->id.'_thumb_'.Str::random(12)).'.webp';
            $image->encodeUsingFormat(
                Format::WEBP,
                quality: 80,
                strip: true,
            )->save($tempPath);

            $pid = $this->video->profile_id;
            $fileName = Str::random(40).'_thumb_'.Str::random(8).'.webp';
            $s3Path = 'videos/'.$pid.'/'.$this->video->id.'/'.$fileName;

            if (! $disk->put($s3Path, file_get_contents($tempPath), 'public')) {
                throw new \RuntimeException('Failed to write optimized thumbnail to S3');
            }

            $this->video->has_thumb = true;
            $this->video->thumbnail_path = $s3Path;
            $this->video->thumbnail_width = $width;
            $this->video->thumbnail_height = $height;
            $this->video->thumbnail_mime = 'image/webp';
            $this->video->save();

            if ($originalPath !== $s3Path) {
                try {
                    $disk->delete($originalPath);
                } catch (\Throwable $cleanupError) {
                    Log::warning('Could not delete old thumbnail after replacement', [
                        'video_id' => $this->video->id,
                        'error' => $cleanupError->getMessage(),
                    ]);
                }
            }

            VideoService::deleteMediaData($this->video->id);

        } catch (\Throwable $e) {
            if (config('logging.dev_log')) {
                Log::error('Custom thumbnail optimization failed', [
                    'video_id' => $this->video->id,
                    'error' => $e->getMessage(),
                ]);
            }

            throw $e;
        } finally {
            if ($tempPath && is_file($tempPath)) {
                @unlink($tempPath);
            }
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('Custom thumbnail job permanently failed, falling back to auto thumbnail', [
            'video_id' => $this->video->id,
            'error' => $exception->getMessage(),
        ]);

        $video = $this->video->fresh();

        if ($video && ! $video->has_thumb) {
            VideoThumbnailJob::dispatch($video);
        }
    }
}
