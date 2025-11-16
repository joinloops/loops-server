<?php

namespace App\Jobs\Video;

use App\Services\VideoService;
use FFMpeg\Format\Video\X264;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use ProtoneMedia\LaravelFFMpeg\Support\FFMpeg;

class VideoOptimizeJob implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $video;

    /**
     * The number of seconds the job can run before timing out.
     *
     * @var int
     */
    public $timeout = 300;

    public $tries = 3;

    /**
     * The maximum number of unhandled exceptions to allow before failing.
     *
     * @var int
     */
    public $maxExceptions = 3;

    public $deleteWhenMissingModels = true;

    /**
     * Create a new job instance.
     */
    public function __construct($video)
    {
        $this->video = $video;
    }

    /**
     * Get the middleware the job should pass through.
     */
    public function middleware(): array
    {
        return [(new WithoutOverlapping('video-processing:'.$this->video->id))->expireAfter(420)];
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        if ($this->batch()->cancelled()) {
            return;
        }

        $video = $this->video;

        if (str_starts_with($video->vid, 'https://')) {
            return;
        }

        $width = 720;
        $crf = 26;

        $ext = pathinfo($video->vid, PATHINFO_EXTENSION);
        $name = str_replace('.'.$ext, '.720p.mp4', $video->vid);

        if ($video->vid_optimized || Storage::disk('s3')->exists($name)) {
            return;
        }

        $format = new X264('aac');
        $format
            ->setAudioKiloBitrate(128)
            ->setAdditionalParameters([
                '-preset', 'slow',
                '-crf', (string) $crf,
                '-profile:v', 'main',
                '-level', '4.0',
                '-movflags', '+faststart',
                '-pix_fmt', 'yuv420p',
                '-ac', '2',
            ]);

        // @phpstan-ignore-next-line
        $media = FFMpeg::fromDisk('s3')
            ->open($video->vid)
            ->addFilter(['-vf', "scale={$width}:-2"])
            ->addFilter('-err_detect', 'ignore_err')
            ->addFilter('-fflags', '+genpts')
            ->export()
            ->toDisk('s3')
            ->inFormat($format)
            ->withVisibility('public')
            ->save($name);

        $video->duration = $media->getDurationInSeconds();
        $video->vid_optimized = $name;
        $video->has_processed = true;
        $video->status = 2;
        $video->save();

        $media->cleanupTemporaryFiles();

        VideoService::deleteMediaData($video->id);
    }
}
