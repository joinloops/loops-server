<?php

namespace App\Jobs\Video;

use App\Services\Media\TranscodeException;
use App\Services\Media\VideoTranscoder;
use App\Services\VideoService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class VideoThumbnailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $video;

    public $timeout = 180;

    public $maxExceptions = 3;

    public $deleteWhenMissingModels = true;

    public $backoff = [10, 30, 60];

    public function __construct($video)
    {
        $this->video = $video->withoutRelations();
        $this->onQueue('video-processing');
    }

    public function retryUntil(): \DateTimeInterface
    {
        return now()->addHours(2);
    }

    public function middleware(): array
    {
        return [
            (new WithoutOverlapping('video-thumb:'.$this->video->id))
                ->releaseAfter(30)
                ->expireAfter($this->timeout + 30),
        ];
    }

    public function handle(VideoTranscoder $transcoder): void
    {
        $video = $this->video->fresh();

        if (! $video) {
            Log::warning('Video not found for thumbnail job', ['video_id' => $this->video->id]);

            return;
        }

        if (! $video->vid || str_starts_with($video->vid, 'https://')) {
            return;
        }

        $workdir = null;

        try {
            $sourcePath = $video->vid_optimized && Storage::disk('s3')->exists($video->vid_optimized)
                ? $video->vid_optimized
                : $video->vid;

            if (! Storage::disk('s3')->exists($sourcePath)) {
                throw new TranscodeException('Video file not found on S3: '.$sourcePath, permanent: true);
            }

            $workdir = $transcoder->makeWorkdir('thumb-'.$video->id);
            $ext = pathinfo($sourcePath, PATHINFO_EXTENSION);
            $source = $workdir.DIRECTORY_SEPARATOR.'source'.($ext ? '.'.strtolower($ext) : '');
            $frame = $workdir.DIRECTORY_SEPARATOR.'thumb.jpg';

            $transcoder->download('s3', $sourcePath, $source);

            $probe = $transcoder->probe($source);
            $streams = VideoTranscoder::selectStreams($probe, $transcoder->decodableCodecs());

            if (! $streams['video']) {
                throw new TranscodeException('No decodable video stream for thumbnail', permanent: true);
            }

            $transcoder->extractFrame($source, $frame, (int) $streams['video']['index'], timeout: $this->timeout - 60);

            $vidExt = pathinfo($video->vid, PATHINFO_EXTENSION);
            $base = $vidExt ? Str::beforeLast($video->vid, '.'.$vidExt) : $video->vid;
            $thumb = $base.'_thumb_'.Str::random(8).'.jpg';

            $transcoder->upload('s3', $frame, $thumb);

            $video->thumbnail_path = $thumb;
            $video->has_thumb = true;
            $video->save();

            VideoService::deleteMediaData($video->id);
        } catch (\Throwable $e) {
            Log::error('Video thumbnail generation failed', [
                'video_id' => $video->id,
                'attempt' => $this->attempts(),
                'error' => $e->getMessage(),
                'ffmpeg' => $e instanceof TranscodeException ? $e->stderrTail() : null,
            ]);

            if ($e instanceof TranscodeException && $e->permanent) {
                $this->fail($e);

                return;
            }

            throw $e;
        } finally {
            $transcoder->cleanup($workdir);
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('Video thumbnail job permanently failed', [
            'video_id' => $this->video->id,
            'error' => $exception->getMessage(),
        ]);
    }
}
