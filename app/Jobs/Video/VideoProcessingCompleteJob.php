<?php

namespace App\Jobs\Video;

use App\Jobs\PushNotifications\SendPushNotificationJob;
use App\Services\CollabService;
use App\Services\ConfigService;
use App\Services\FederationDispatcher;
use App\Services\VideoService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class VideoProcessingCompleteJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $video;

    public $timeout = 60;

    public $tries = 3;

    public $deleteWhenMissingModels = true;

    public function __construct($video)
    {
        $this->video = $video->withoutRelations();
    }

    public function handle(): void
    {
        $video = $this->video->fresh();

        if (! $video) {
            Log::warning('Video not found for completion job', ['video_id' => $this->video->id]);

            return;
        }

        try {
            $video->processing_status = 'completed';
            $video->save();

            if (app(CollabService::class)->blocksPublish($video)) {
                return;
            }

            $scheduler = app(\App\Services\VideoScheduleService::class);

            if ($scheduler->shouldHoldForSchedule($video)) {
                return;
            }

            if ($video->isScheduled()) {
                if ($scheduler->claim($video->id)) {
                    $scheduler->publish($video->fresh());
                }

                return;
            }

            if (! $video->isLive() && $video->has_processed) {
                DB::table('videos')->where('id', $video->id)->where('status', '!=', 2)->update(['status' => 2]);
                $video = $video->fresh();
                VideoService::deleteMediaData($video->id);
            }

            $config = app(ConfigService::class);

            if ($config->federation() && ! $video->federated_at) {
                app(FederationDispatcher::class)->dispatchVideoCreation($video);

                $video->federated_at = now();
                $video->ap_published_at = now();
                $video->save();
            }

            if ($config->pushNotifications()) {
                SendPushNotificationJob::dispatch_videoProcessed(
                    profileId: $video->profile_id,
                    videoId: $video->id,
                );
            }
        } catch (\Exception $e) {
            Log::error('Video completion processing failed', [
                'video_id' => $video->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('Video completion job permanently failed', [
            'video_id' => $this->video->id,
            'error' => $exception->getMessage(),
        ]);
    }
}
