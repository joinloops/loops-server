<?php

namespace App\Jobs\Video;

use App\Models\Video;
use App\Services\Media\TranscodeException;
use App\Services\Media\VideoTranscoder;
use App\Services\VideoService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class VideoOptimizeJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $video;

    public $timeout;

    public $maxExceptions = 3;

    public $backoff = [30, 120, 300];

    public $deleteWhenMissingModels = true;

    public const MAX_DURATION_SECONDS = 180;

    private const UPLOAD_HEADROOM_SECONDS = 90;

    public function __construct($video)
    {
        $this->video = $video->withoutRelations();
        $this->timeout = max(120, (int) config('loops.media.transcode.job_timeout', 900));
        $this->onQueue('video-processing');
    }

    public function retryUntil(): \DateTimeInterface
    {
        return now()->addHours(max(1, (int) config('loops.media.transcode.retry_window_hours', 6)));
    }

    public function middleware(): array
    {
        return [
            (new WithoutOverlapping('video-processing:'.$this->video->id))
                ->releaseAfter(60)
                ->expireAfter($this->timeout + 60),
        ];
    }

    public static function failureCountKey(int $videoId): string
    {
        return 'loops:transcode:failures:'.$videoId;
    }

    public function handle(VideoTranscoder $transcoder): void
    {
        $startedAt = time();
        $video = $this->video->fresh();

        if (! $video) {
            Log::warning('Video not found for optimization job', ['video_id' => $this->video->id]);

            return;
        }

        if (! $video->vid || str_starts_with($video->vid, 'https://')) {
            return;
        }

        $isScheduled = (int) $video->publish_state === Video::PUBLISH_STATE_SCHEDULED;

        if ($video->vid_optimized) {
            $this->markProcessed($video, $isScheduled);

            return;
        }

        $failureKey = self::failureCountKey($video->id);
        $previousFailures = (int) Cache::get($failureKey, 0);
        $safeMode = $previousFailures > 0;
        $workdir = null;

        try {
            if (! Storage::disk('s3')->exists($video->vid)) {
                throw new TranscodeException('Source video file not found on S3: '.$video->vid, permanent: true);
            }

            $transcoder->pruneStaleWorkdirs();
            $workdir = $transcoder->makeWorkdir('video-'.$video->id);

            $ext = pathinfo($video->vid, PATHINFO_EXTENSION);
            $source = $workdir.DIRECTORY_SEPARATOR.'source'.($ext ? '.'.strtolower($ext) : '');
            $output = $workdir.DIRECTORY_SEPARATOR.'optimized.mp4';

            $transcoder->download('s3', $video->vid, $source);

            $probe = $transcoder->probe($source);

            $settings = [
                'short_edge' => (int) config('loops.media.transcode.short_edge', 720),
                'crf' => (int) config('loops.media.transcode.crf', 23),
                'preset' => (string) config('loops.media.transcode.preset', 'slow'),
                'fallback_preset' => (string) config('loops.media.transcode.fallback_preset', 'veryfast'),
                'audio_kbps' => (int) config('loops.media.transcode.audio_kbps', 128),
                'advanced' => (bool) config('loops.media.transcode.advanced', false),
                'max_fps' => (int) config('loops.media.transcode.max_fps', 60),
                'threads' => config('laravel-ffmpeg.ffmpeg.threads'),
                'max_duration' => self::MAX_DURATION_SECONDS,
                'safe_mode' => $safeMode,
            ];

            $plan = VideoTranscoder::plan($probe, $settings, $transcoder->decodableCodecs());

            if ($plan['undecodable_audio']) {
                Log::warning('Video has no decodable audio track, transcoding video only', [
                    'video_id' => $video->id,
                    'streams' => collect($probe['streams'])->map(fn ($s) => ($s['codec_type'] ?? '?').':'.($s['codec_name'] ?? '?'))->all(),
                ]);
            }

            $ffmpegBudget = $this->timeout - (time() - $startedAt) - self::UPLOAD_HEADROOM_SECONDS;
            $transcoder->transcode($source, $output, $plan, $settings, $ffmpegBudget);

            $outputProbe = $transcoder->probe($output);
            $outputVideo = collect($outputProbe['streams'])->firstWhere('codec_type', 'video');
            $duration = (float) ($outputProbe['format']['duration'] ?? 0);

            if (! $outputVideo || $duration <= 0) {
                throw new TranscodeException('Optimized output has no playable video stream');
            }

            $base = $ext ? Str::beforeLast($video->vid, '.'.$ext) : $video->vid;
            $name = $base.Str::random(20).'.720p.mp4';

            $transcoder->upload('s3', $output, $name);

            $video->width = (int) ($outputVideo['width'] ?? $plan['width']);
            $video->height = (int) ($outputVideo['height'] ?? $plan['height']);
            $video->duration = max(1, (int) round($duration));
            $video->vid_optimized = $name;
            $video->has_audio = $plan['audio_index'] !== null;
            $this->markProcessed($video, $isScheduled);

            Cache::forget($failureKey);
            VideoService::deleteMediaData($video->id);

            if ($safeMode) {
                Log::info('Video optimized in safe mode after earlier failure', [
                    'video_id' => $video->id,
                    'previous_failures' => $previousFailures,
                ]);
            }
        } catch (\Throwable $e) {
            Cache::add($failureKey, 0, now()->addDay());
            Cache::increment($failureKey);

            Log::error('Video optimization failed', [
                'video_id' => $video->id,
                'attempt' => $this->attempts(),
                'safe_mode' => $safeMode,
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

    private function markProcessed(Video $video, bool $isScheduled): void
    {
        $video->has_processed = true;

        if ($video->processing_status === 'failed') {
            $video->processing_status = 'processing';
            $video->processing_error = null;
            $video->processing_failed_at = null;
        }

        if (! $isScheduled) {
            $video->status = 2;
        }

        $video->save();
    }

    public function failed(\Throwable $exception): void
    {
        $video = $this->video->fresh();

        $message = $exception->getMessage();
        if ($exception instanceof TranscodeException && $exception->stderr !== '') {
            $message .= ' | '.TranscodeException::tail($exception->stderr, 500);
        }

        if ($video) {
            $video->processing_status = 'failed';
            $video->processing_error = Str::limit('Optimization: '.$message, 1000);
            $video->processing_failed_at = now();
            $video->save();

            if ($video->isScheduled()) {
                app(\App\Services\VideoScheduleService::class)
                    ->markFailed($video, 'transcode_failed');
            }
        }

        Log::error('Video optimization job permanently failed', [
            'video_id' => $this->video->id,
            'error' => $message,
        ]);
    }
}
