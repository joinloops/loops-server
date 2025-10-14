<?php

namespace App\Jobs\Federation;

use App\Federation\Handlers\DeleteHandler;
use App\Federation\Validators\DeleteValidator;
use App\Models\Profile;
use App\Services\SanitizeService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessSharedInboxActivity implements ShouldQueue
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

    /**
     * Create a new job instance.
     */
    public function __construct(array $activity, ?Profile $actor)
    {
        $this->activity = $activity;
        $this->actor = $actor;
    }

    /**
     * Execute the job.
     */
    public function handle()
    {
        if (config('logging.dev_log')) {
            Log::info('Processing shared inbox activity', [
                'type' => $this->activity['type'] ?? 'unknown',
                'id' => $this->activity['id'] ?? null,
            ]);
        }

        try {
            // Check if Delete Activity first, it will have no recipients
            if (isset($this->activity['type']) && $this->activity['type'] === 'Delete') {
                $this->handleDeleteActivity();

                return;
            }

            $recipients = $this->extractRecipients();

            if (empty($recipients)) {
                if (config('logging.dev_log')) {
                    Log::info('No recipient URIs found in shared inbox activity', [
                        'activity_id' => $this->activity['id'] ?? null,
                        'type' => $this->activity['type'] ?? 'unknown',
                    ]);
                }

                return;
            }

            $localTargets = $this->findLocalTargets($recipients);

            if ($localTargets->isEmpty()) {
                if (config('logging.dev_log')) {
                    Log::info('No local targets found for shared inbox activity', [
                        'activity_id' => $this->activity['id'] ?? null,
                        'recipients' => $recipients,
                    ]);
                }

                return;
            }

            foreach ($localTargets as $target) {
                ProcessInboxActivity::dispatch($this->activity, $this->actor, $target)->onQueue('activitypub-in');
            }

            if (config('logging.dev_log')) {
                Log::info('Dispatched shared inbox activity to local targets', [
                    'activity_id' => $this->activity['id'] ?? null,
                    'target_count' => $localTargets->count(),
                    'targets' => $localTargets->pluck('username')->toArray(),
                ]);
            }

        } catch (\Exception $e) {
            if (config('logging.dev_log')) {
                Log::error('Failed to process shared inbox activity', [
                    'type' => $this->activity['type'] ?? 'unknown',
                    'id' => $this->activity['id'],
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }
            throw $e;
        }
    }

    /**
     * Extract recipient URIs from the activity's 'to' and 'cc' fields
     */
    protected function extractRecipients(): array
    {
        $recipients = [];

        // Extract from 'to' field
        if (isset($this->activity['to'])) {
            $recipients = array_merge($recipients, $this->normalizeRecipients($this->activity['to']));
        }

        // Extract from 'cc' field
        if (isset($this->activity['cc'])) {
            $recipients = array_merge($recipients, $this->normalizeRecipients($this->activity['cc']));
        }

        $recipients = array_unique($recipients);
        $recipients = array_filter($recipients, function ($recipient) {
            return ! empty($recipient) &&
                   app(SanitizeService::class)->isLocalObject($recipient) !== false &&
                   ! str_contains($recipient, 'www.w3.org/ns/activitystreams');
        });

        return array_values($recipients);
    }

    /**
     * Normalize recipients (handle both string and array formats)
     */
    protected function normalizeRecipients($recipients): array
    {
        if (is_string($recipients)) {
            return [$recipients];
        }

        if (is_array($recipients)) {
            return $recipients;
        }

        return [];
    }

    /**
     * Find local profiles that match the recipient URIs
     */
    protected function findLocalTargets(array $recipients)
    {
        $localTargets = collect();

        foreach ($recipients as $recipient) {
            $profileMatch = app(SanitizeService::class)->matchUrlTemplate(
                url: $recipient,
                templates: [
                    '/ap/users/{profileId}',
                    '/ap/users/{profileId}/followers',
                ],
                useAppHost: true,
                constraints: ['profileId' => '\d+']
            );

            if ($profileMatch) {
                if (isset($profileMatch['profileId'])) {
                    $res = Profile::whereLocal(true)->find($profileMatch['profileId']);
                    if ($res) {
                        $localTargets->push($res);
                    }
                }
            }
        }

        return $localTargets->unique('id');
    }

    /**
     * Determine the time at which the job should timeout.
     */
    public function retryUntil()
    {
        return now()->addHours(2);
    }

    /**
     * Handle Delete activities without requiring to/cc fields
     */
    protected function handleDeleteActivity()
    {
        $validator = app(DeleteValidator::class);

        if (! $validator->validate($this->activity)) {
            if (config('logging.dev_log')) {
                Log::warning('Delete activity failed validation', [
                    'activity_id' => $this->activity['id'] ?? null,
                ]);
            }

            return;
        }

        $handler = app(DeleteHandler::class);
        $handler->handle($this->activity, $this->actor);

        if (config('logging.dev_log')) {
            Log::info('Processed Delete activity', [
                'activity_id' => $this->activity['id'] ?? null,
            ]);
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception)
    {
        if (config('logging.dev_log')) {
            Log::error('Shared inbox activity processing failed permanently', [
                'type' => $this->activity['type'] ?? 'unknown',
                'activity_id' => $this->activity['id'] ?? null,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
