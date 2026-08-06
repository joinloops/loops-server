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

            $rotation = 0;

            if ($videoStream->has('side_data_list')) {
                foreach ((array) $videoStream->get('side_data_list') as $sideData) {
                    if (isset($sideData['rotation'])) {
                        $rotation = abs((int) $sideData['rotation']) % 180;
                    }
                }
            }

            if ($rotation === 0 && $videoStream->has('tags')) {
                $tags = (array) $videoStream->get('tags');
                if (isset($tags['rotate'])) {
                    $rotation = abs((int) $tags['rotate']) % 180;
                }
            }

            if ($rotation === 90) {
                [$width, $height] = [$height, $width];
            }

            $shortEdge = (int) config('loops.media.transcode.short_edge', 720);
            $crf = (string) config('loops.media.transcode.crf', 23);
            $preset = (string) config('loops.media.transcode.preset', 'slow');
            $advanced = (bool) config('loops.media.transcode.advanced', false);

            $scaleFactor = ($shortEdge / 720) ** 2;

            if ($height > $width) {
                $scaleFilter = "scale={$shortEdge}:-2";
                $baseKbps = 2500;
                $video->width = $shortEdge;
                $video->height = (int) round($height * ($shortEdge / $width) / 2) * 2;
            } elseif ($width > $height) {
                $scaleFilter = "scale=-2:{$shortEdge}";
                $baseKbps = 3000;
                $video->width = (int) round($width * ($shortEdge / $height) / 2) * 2;
                $video->height = $shortEdge;
            } else {
                $scaleFilter = "scale={$shortEdge}:{$shortEdge}";
                $baseKbps = 2500;
                $video->width = $shortEdge;
                $video->height = $shortEdge;
            }

            $maxKbps = (int) round($baseKbps * $scaleFactor);

            $fps = 30;
            if ($videoStream->has('avg_frame_rate')) {
                $parts = explode('/', (string) $videoStream->get('avg_frame_rate'));
                if (count($parts) === 2 && (float) $parts[1] > 0) {
                    $fps = (int) round((float) $parts[0] / (float) $parts[1]) ?: 30;
                }
            }
            $fps = max(1, min($fps, 60));

            $params = [
                '-preset', $preset,
                '-crf', $crf,
                '-maxrate', $maxKbps.'k',
                '-bufsize', ($maxKbps * 2).'k',
                '-profile:v', 'high',
                '-level', '4.1',
                '-movflags', '+faststart',
                '-pix_fmt', 'yuv420p',
                '-ac', '2',
                '-t', (string) $maxDuration,
            ];

            if ($advanced) {
                $params = array_merge($params, [
                    '-g', (string) ($fps * 2),
                    '-keyint_min', (string) $fps,
                    '-x264-params', 'aq-mode=3:aq-strength=0.9:psy-rd=1.00,0.15:ref=4:bframes=3',
                    '-color_primaries', 'bt709',
                    '-color_trc', 'bt709',
                    '-colorspace', 'bt709',
                ]);
            } else {
                $params = array_merge($params, ['-nal-hrd', 'vbr', '-tune', 'film']);
            }

            $format = new X264('aac');
            $format
                ->setAudioKiloBitrate((int) config('loops.media.transcode.audio_kbps', 128))
                ->setKiloBitrate(0)
                ->setAdditionalParameters($params);

            $media = FFMpeg::fromDisk('s3')
                ->open($video->vid)
                ->addFilter(['-vf', $scaleFilter.',format=yuv420p'])
                ->addFilter('-sws_flags', 'lanczos')
                ->addFilter('-err_detect', 'ignore_err')
                ->addFilter('-fflags', '+genpts')
            // @phpstan-ignore-next-line
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

                $this->fail($e);
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
