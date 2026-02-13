<?php

namespace App\Jobs\Video;

use App\Services\VideoService;
use FFMpeg\Format\Video\X264;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use ProtoneMedia\LaravelFFMpeg\Support\FFMpeg;

class VideoOptimizeJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $video;

    public $timeout = 300;

    public $tries = 3;

    public $maxExceptions = 3;

    public $deleteWhenMissingModels = true;

    public function __construct($video)
    {
        $this->video = $video->withoutRelations();
        $this->onQueue('video-processing');
    }

    public function middleware(): array
    {
        return [(new WithoutOverlapping('video-processing:'.$this->video->id))->expireAfter(420)];
    }

    public function handle(): void
    {
        $video = $this->video->fresh();

        if (! $video) {
            Log::warning('Video not found for optimization job', ['video_id' => $this->video->id]);

            return;
        }

        if (str_starts_with($video->vid, 'https://')) {
            return;
        }

        try {
            if (! Storage::disk('s3')->exists($video->vid)) {
                throw new \Exception('Source video file not found on S3: '.$video->vid);
            }

            $maxDuration = 180;
            $ext = pathinfo($video->vid, PATHINFO_EXTENSION);
            $name = str_replace('.'.$ext, '.720p.mp4', $video->vid);

            if ($video->vid_optimized || Storage::disk('s3')->exists($name)) {
                $video->has_processed = true;
                $video->status = 2;
                $video->save();

                return;
            }

            $mediaInfo = FFMpeg::fromDisk('s3')->open($video->vid);
            $videoStream = $mediaInfo->getVideoStream();

            if (! $videoStream) {
                throw new \Exception('Could not read video stream from file');
            }

            $hasAudio = false;
            try {
                $audioStream = $mediaInfo->getAudioStream();
                $hasAudio = $audioStream !== null;
            } catch (\Exception $e) {
                $hasAudio = false;
            }

            $width = $videoStream->get('width');
            $height = $videoStream->get('height');

            if (! $width || ! $height) {
                throw new \Exception('Could not determine video dimensions');
            }

            if ($height > $width) {
                $scaleFilter = 'scale=720:-2';
                $maxBitrate = '2500k';
                $bufSize = '5000k';
            } elseif ($width > $height) {
                $scaleFilter = 'scale=-2:720';
                $maxBitrate = '3000k';
                $bufSize = '6000k';
            } else {
                $scaleFilter = 'scale=720:720';
                $maxBitrate = '2500k';
                $bufSize = '5000k';
            }

            $format = new X264('aac');
            $format
                ->setAudioKiloBitrate(128)
                ->setKiloBitrate(0)
                ->setAdditionalParameters([
                    '-preset', 'slow',
                    '-crf', '23',
                    '-maxrate', $maxBitrate,
                    '-bufsize', $bufSize,
                    '-nal-hrd', 'vbr',
                    '-profile:v', 'high',
                    '-level', '4.1',
                    '-movflags', '+faststart',
                    '-pix_fmt', 'yuv420p',
                    '-tune', 'film',
                    '-ac', '2',
                    '-t', (string) $maxDuration,
                ]);

            // @phpstan-ignore-next-line
            $media = FFMpeg::fromDisk('s3')
                ->open($video->vid)
                ->addFilter(['-vf', $scaleFilter.',format=yuv420p'])
                ->addFilter('-sws_flags', 'lanczos')
                ->addFilter('-err_detect', 'ignore_err')
                ->addFilter('-fflags', '+genpts')
                ->export()
                ->toDisk('s3')
                ->inFormat($format)
                ->withVisibility('public')
                ->save($name);

            // @phpstan-ignore-next-line
            if (! Storage::disk('s3')->exists($name)) {
                throw new \Exception('Optimized video was not created on S3');
            }

            // @phpstan-ignore-next-line
            $video->duration = $media->getDurationInSeconds();
            $video->vid_optimized = $name;
            $video->has_processed = true;
            $video->has_audio = (bool) $hasAudio;
            $video->status = 2;
            $video->save();

            $media->cleanupTemporaryFiles();
            VideoService::deleteMediaData($video->id);
        } catch (\Exception $e) {
            Log::error('Video optimization failed', [
                'video_id' => $video->id,
                'attempt' => $this->attempts(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            if ($this->attempts() >= $this->tries) {
                $video->processing_error = 'Optimization failed: '.$e->getMessage();
                $video->processing_status = 'failed';
                $video->processing_failed_at = now();
                $video->save();

                return;
            }

            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        $video = $this->video->fresh();

        if ($video) {
            $video->processing_status = 'failed';
            $video->processing_error = 'Optimization: '.$exception->getMessage();
            $video->processing_failed_at = now();
            $video->save();
        }

        Log::error('Video optimization job permanently failed', [
            'video_id' => $this->video->id,
            'error' => $exception->getMessage(),
        ]);
    }
}
