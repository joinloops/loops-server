<?php

namespace App\Jobs\Video;

use App\Models\Video;
use FFMpeg\Format\Video\X264;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use ProtoneMedia\LaravelFFMpeg\Exporters\EncodingException;
use ProtoneMedia\LaravelFFMpeg\Filesystem\Media;
use ProtoneMedia\LaravelFFMpeg\Support\FFMpeg;

class CompositeDuetVideo implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 600;

    public $tries = 3;

    protected Video $video;

    /**
     * Create a new job instance.
     */
    public function __construct(Video $video)
    {
        $this->video = $video;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $disk = 's3';

        try {
            $this->video->refresh();

            $originalLoopId = $this->video->original_duet_id;
            $originalLoop = Video::published()->where('can_duet', true)->find($originalLoopId);

            if (! $originalLoop || ! $originalLoop->vid_optimized) {
                throw new \Exception('Missing original or upload paths for duet.');
            }

            $ogVideoPath = $this->video->vid;
            $composedVideoPath = str_replace('.mp4', '_duet.1080p.mp4', $this->video->vid);
            $layout = $this->video->duet_layout == 1 ? 'side-by-side' : 'vertical';

            $res = $this->compositeVideosToS3(
                $disk,
                $originalLoop->vid_optimized,
                $this->video->vid,
                $composedVideoPath,
                $layout
            );
        } catch (EncodingException $e) {
            Log::error("Duet composition failed (FFmpeg) for duet ID: {$this->video->id}", [
                'error' => $e->getMessage(),
                'command' => $e->getCommand(),
                'output' => $e->getErrorOutput(),
            ]);

            throw $e;
        } catch (\Throwable $e) {
            Log::error("Duet composition failed for duet ID: {$this->video->id}", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        } finally {
            try {
                if (isset($composedVideoPath) && ! empty($composedVideoPath)) {
                    $this->video->update([
                        'vid_optimized' => $composedVideoPath,
                        'duration' => $res['duration'] ?? 0,
                        'has_processed' => true,
                        'status' => 2,
                    ]);
                }
                FFMpeg::cleanupTemporaryFiles();
            } catch (\Throwable $cleanupException) {
                Log::warning('Failed to cleanup FFmpeg temporary files', [
                    'error' => $cleanupException->getMessage(),
                ]);
            }
        }
    }

    /**
     * Composite two videos directly from S3 into a new S3 object using laravel-ffmpeg.
     */
    protected function compositeVideosToS3(
        string $disk,
        string $originalVideoPath,
        string $responseVideoPath,
        string $outputPath,
        string $layout
    ) {
        $format = new X264('aac', 'libx264');
        $format->setAudioKiloBitrate(128);
        $format->setAdditionalParameters([
            '-preset', 'slow',
            '-crf', '23',
            '-pix_fmt', 'yuv420p',
            '-movflags', '+faststart',
            '-ac', '2',
        ]);

        if ($layout === 'side-by-side') {
            $media1 = FFMpeg::fromDisk($disk)->open($originalVideoPath);
            $media2 = FFMpeg::fromDisk($disk)->open($responseVideoPath);

            $video1 = $media1->getVideoStream();
            $video2 = $media2->getVideoStream();

            $width1 = $video1->get('width');
            $height1 = $video1->get('height');
            $width2 = $video2->get('width');
            $height2 = $video2->get('height');

            $scaledHeight1 = (int) round(360 * ($height1 / $width1) / 2) * 2;
            $scaledHeight2 = (int) round(360 * ($height2 / $width2) / 2) * 2;

            $maxHeight = max($scaledHeight1, $scaledHeight2);

            $exporter = FFMpeg::fromDisk($disk)
                ->open([$originalVideoPath, $responseVideoPath])
                ->export()
                ->addFilter('[0:v]', "scale=360:-2,setsar=1,pad=360:{$maxHeight}:(ow-iw)/2:(oh-ih)/2", '[left]')
                ->addFilter('[1:v]', "scale=360:-2,setsar=1,pad=360:{$maxHeight}:(ow-iw)/2:(oh-ih)/2", '[right]')
                ->addFilter('[left][right]', 'hstack=inputs=2', '[v]')
                ->addFilter('[0:a]', 'volume=0.5', '[a0]')
                ->addFilter('[1:a]', 'volume=1.0', '[a1]')
                ->addFilter('[a0][a1]', 'amix=inputs=2:duration=shortest', '[a]');
        } else {
            $targetWidth = 1080;
            $targetHeight = 1920;
            $halfHeight = 960;

            $exporter = FFMpeg::fromDisk($disk)
                ->open([$originalVideoPath, $responseVideoPath])
                ->export()
                ->addFilter(
                    '[0:v]',
                    "scale=-2:{$halfHeight}:force_original_aspect_ratio=decrease,".
                    "pad={$targetWidth}:{$halfHeight}:(ow-iw)/2:(oh-ih)/2:color=black,".
                    'setsar=1',
                    '[top]'
                )
                ->addFilter(
                    '[1:v]',
                    "scale=-2:{$halfHeight}:force_original_aspect_ratio=decrease,".
                    "pad={$targetWidth}:{$halfHeight}:(ow-iw)/2:(oh-ih)/2:color=black,".
                    'setsar=1',
                    '[bottom]'
                )
                ->addFilter('[top][bottom]', 'vstack=inputs=2', '[v]')
                ->addFilter('[0:a]', 'volume=0.5', '[a0]')
                ->addFilter('[1:a]', 'volume=1.0', '[a1]')
                ->addFilter('[a0][a1]', 'amix=inputs=2:duration=shortest', '[a]');
        }

        // @phpstan-ignore-next-line
        $exporter->addFormatOutputMapping(
            $format,
            Media::make($disk, $outputPath),
            ['[v]', '[a]']
        )
            ->withVisibility('public')
            ->save();

        return ['duration' => $exporter->getDurationInSeconds()];
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error("Duet composition job failed permanently for duet ID: {$this->video->id}", [
            'error' => $exception->getMessage(),
        ]);
    }
}
