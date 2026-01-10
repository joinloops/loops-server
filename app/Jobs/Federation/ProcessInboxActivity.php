<?php

namespace App\Jobs\Federation;

use App\Models\Profile;
use App\Services\ActivityService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessInboxActivity implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The activity data to process
     */
    public $activity;

    /**
     * The actor who sent the activity
     */
    public $actor;

    /**
     * The target of the activity
     */
    public $target;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 3;

    /**
     * The number of seconds the job can run before timing out.
     *
     * @var int
     */
    public $timeout = 60;

    public $backoff = [10, 30, 40];

    public $maxExceptions = 2;

    public $deleteWhenMissingModels = false;

    /**
     * Create a new job instance.
     */
    public function __construct(array $activity, Profile $actor, Profile $target)
    {
        $this->activity = $activity;
        $this->actor = $actor;
        $this->target = $target;
    }

    /**
     * Execute the job.
     */
    public function handle(ActivityService $activityService)
    {
        if (config('logging.dev_log')) {
            Log::info('Processing incoming activity', [
                'type' => $this->activity['type'] ?? 'unknown',
                'actor' => $this->actor->uri ?? $this->actor->username,
                'id' => $this->activity['id'] ?? null,
            ]);
        }

        try {
            $result = $activityService->processIncomingActivity($this->activity, $this->actor, $this->target);

            return $result;
        } catch (\Exception $e) {
            if (config('logging.dev_log')) {
                Log::error('Failed to process activity', [
                    'type' => $this->activity['type'] ?? 'unknown',
                    'actor' => $this->actor->uri ?? $this->actor->username,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }
            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception)
    {
        if (config('logging.dev_log')) {
            Log::error('Inbox activity processing failed permanently', [
                'type' => $this->activity['type'] ?? 'unknown',
                'actor' => $this->actor->uri ?? $this->actor->username,
                'activity_id' => $this->activity['id'] ?? null,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
