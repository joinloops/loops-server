<?php

namespace App\Jobs\Video;

use App\Models\Video;
use App\Services\AudioFingerprintService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use ProtoneMedia\LaravelFFMpeg\Support\FFMpeg;
use Throwable;

class ProcessVideoAudioJob implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 600;

    public $tries = 3;

    public $backoff = [60, 120, 300];

    public $deleteWhenMissingModels = true;

    public $failOnTimeout = true;

    public function __construct(public readonly int $videoId) {}

    public function uniqueId(): string
    {
        return 'video-audio:'.$this->videoId;
    }

    public function middleware(): array
    {
        return [
            (new WithoutOverlapping($this->uniqueId()))
                ->expireAfter(3600)
                ->dontRelease(),
        ];
    }

    public function tags(): array
    {
        return ['video:'.$this->videoId, 'audio-fingerprint'];
    }

    public function handle(AudioFingerprintService $fingerprintService): void
    {
        $video = Video::query()
            ->select([
                'id',
                'profile_id',
                'duration',
                'audio_allow_reuse',
                'audio_processed_at',
                'has_processed',
                'has_audio',
                'sound_id',
                'vid',
                'vid_optimized',
            ])
            ->findOrFail($this->videoId);

        if ($video->audio_processed_at) {
            return;
        }

        $sourcePath = $video->vid_optimized ?: $video->vid;

        if (! $sourcePath) {
            Log::warning('ProcessVideoAudioJob: missing source path', ['video_id' => $video->id]);
            $this->markAsNoAudio($video);

            return;
        }

        if (! $this->hasAudioTrack($video, $sourcePath)) {
            $this->markAsNoAudio($video);

            return;
        }

        try {
            $sound = $fingerprintService->processVideo(
                $video->mediaUrl(),
                $video->id,
                (string) $video->profile_id,
                $video->duration,
                $video->audio_allow_reuse,
            );

            $video->updateQuietly([
                'sound_id' => $sound->id,
                'audio_processed_at' => now(),
                'has_audio' => true,
            ]);
        } catch (\RuntimeException $e) {
            if (str_contains($e->getMessage(), 'Empty fingerprint') ||
                str_contains($e->getMessage(), 'Failed to generate fingerprint')) {

                if (config('logging.dev_log')) {
                    Log::warning("Unable to generate fingerprint for video {$video->id}", [
                        'video_id' => $video->id,
                        'error' => $e->getMessage(),
                        'attempt' => $this->attempts(),
                    ]);
                }

                $this->markAsNoAudio($video, 'fingerprint_failed');

                return;
            }

            throw $e;
        } catch (Throwable $e) {
            Log::error("Failed to process audio for video {$video->id}: {$e->getMessage()}", [
                'exception' => $e,
                'video_id' => $video->id,
                'video_path' => $video->vid,
                'attempt' => $this->attempts(),
            ]);

            throw $e;
        }
    }

    protected function hasAudioTrack(Video $video, string $path): bool
    {
        try {
            $media = FFMpeg::fromDisk('s3')->open($path);

            $res = $media->getAudioStream() !== null;
            $media->cleanupTemporaryFiles();

            return $res;
        } catch (Throwable $e) {
            Log::warning('ffprobe failed while checking audio stream', [
                'video_id' => $video->id,
                'path' => $path,
                'error' => $e->getMessage(),
                'attempt' => $this->attempts(),
            ]);

            throw $e;
        }
    }

    protected function markAsNoAudio(Video $video, ?string $reason = null): void
    {
        $video->updateQuietly([
            'audio_processed_at' => now(),
            'has_audio' => false,
            'sound_id' => null,
        ]);

        if ($reason) {
            if (config('logging.dev_log')) {
                Log::info("Video {$video->id} marked as no audio", [
                    'video_id' => $video->id,
                    'reason' => $reason,
                ]);
            }
        }
    }

    public function failed(Throwable $exception): void
    {
        Log::error("ProcessVideoAudioJob failed permanently for video {$this->videoId}", [
            'error' => $exception->getMessage(),
        ]);
    }
}
