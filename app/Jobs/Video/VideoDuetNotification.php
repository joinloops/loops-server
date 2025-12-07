<?php

namespace App\Jobs\Video;

use App\Models\Video;
use App\Services\NotificationService;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class VideoDuetNotification implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

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
        $video = $this->video;
        $videoPid = $video->profile_id;

        $ogVideo = Video::published()->where('can_duet', true)->find($video->original_duet_id);

        if (! $ogVideo || ! $ogVideo->is_local || $ogVideo->profile_id == $video->profile_id) {
            return;
        }

        NotificationService::newVideoDuet($video->profile_id, $ogVideo->profile_id, $video->id);
    }
}
