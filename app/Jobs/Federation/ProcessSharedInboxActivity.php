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

    public $maxExceptions = 1;

    public $deleteWhenMissingModels = true;

    /**
     * Calculate the number of seconds to wait before retrying the job.
     *
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [1, 10, 15];
    }

    /**
     * Create a new job instance.
     */
    public function __construct(array $activity, $actor)
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
            $actorUrl = $this->activity['actor'] ?? null;
            if ($actorUrl) {
                $relayService = app(\App\Services\RelayService::class);
                $type = $this->activity['type'] ?? null;

                if (in_array($type, ['Accept', 'Reject'])) {
                    $relay = $relayService->findRelayByActor($actorUrl);
                } else {
                    $relay = $relayService->isFromRelay($actorUrl);
                }
                if ($relay) {
                    $this->handleRelayActivity($relay);

                    return;
                }
            }

            if (isset($this->activity['type']) && $this->activity['type'] === 'Delete') {
                $this->handleDeleteActivity();

                return;
            }

            if (isset($this->activity['type']) && $this->activity['type'] === 'Follow') {
                $this->handleFollowActivity();

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

            $chunkSize = config('loops.federation.inbox_dispatch_chunk_size', 100);
            $targetCount = $localTargets->count();

            $localTargets->chunk($chunkSize)->each(function ($chunk) {
                foreach ($chunk as $target) {
                    ProcessInboxActivity::dispatch($this->activity, $this->actor, $target)
                        ->onQueue('activitypub-in');
                }
            });

            if (config('logging.dev_log')) {
                Log::info('Dispatched shared inbox activity to local targets', [
                    'activity_id' => $this->activity['id'] ?? null,
                    'target_count' => $targetCount,
                    'chunks' => ceil($targetCount / $chunkSize),
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
            return ! empty($recipient) && ! str_contains($recipient, 'www.w3.org/ns/activitystreams');
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
     * Handles both direct local profile URLs and remote followers URLs
     */
    protected function findLocalTargets(array $recipients)
    {
        $localTargetIds = collect();

        foreach ($recipients as $recipient) {
            if (app(SanitizeService::class)->isLocalObject($recipient)) {
                $profileMatch = app(SanitizeService::class)->matchUrlTemplate(
                    url: $recipient,
                    templates: [
                        '/ap/users/{profileId}',
                        '/ap/users/{profileId}/followers',
                    ],
                    useAppHost: true,
                    constraints: ['profileId' => '\d+']
                );

                if ($profileMatch && isset($profileMatch['profileId'])) {
                    if (Profile::whereLocal(true)->where('id', $profileMatch['profileId'])->exists()) {
                        $localTargetIds->push($profileMatch['profileId']);
                    }
                }
            } else {
                $remoteProfile = Profile::where('followers_url', $recipient)
                    ->select('id', 'username')
                    ->first();

                if ($remoteProfile) {
                    $maxFollowers = config('loops.federation.inbox_max_followers', 5000);

                    $followerQuery = $remoteProfile->followers()->where('profile_is_local', true)->limit($maxFollowers);

                    $followerIds = $followerQuery->pluck('followers.profile_id');

                    if ($followerIds->isNotEmpty()) {
                        $localTargetIds = $localTargetIds->merge($followerIds);

                        if (config('logging.dev_log')) {
                            $totalFollowers = $remoteProfile->followers()->where('profile_is_local', true)->count();
                            Log::info('Found local followers for remote profile', [
                                'remote_profile' => $remoteProfile->username,
                                'followers_url' => $recipient,
                                'local_followers_count' => $followerIds->count(),
                                'total_followers' => $totalFollowers,
                                'limited' => $totalFollowers > $maxFollowers,
                            ]);
                        }
                    }
                }
            }
        }

        $uniqueIds = $localTargetIds->unique()->values();

        if ($uniqueIds->isEmpty()) {
            return collect();
        }

        return Profile::whereIn('id', $uniqueIds)->whereLocal(true)->get();
    }

    /**
     * Handle Follow activities - extract target from object field
     */
    protected function handleFollowActivity()
    {
        if (! isset($this->activity['object'])) {
            if (config('logging.dev_log')) {
                Log::warning('Follow activity missing object field', [
                    'activity_id' => $this->activity['id'] ?? null,
                ]);
            }

            return;
        }

        $objectUrl = $this->activity['object'];

        // Check if this is a local object
        if (! app(SanitizeService::class)->isLocalObject($objectUrl)) {
            if (config('logging.dev_log')) {
                Log::info('Follow activity object is not local', [
                    'activity_id' => $this->activity['id'] ?? null,
                    'object' => $objectUrl,
                ]);
            }

            return;
        }

        // Find the local target profile
        $target = $this->findLocalTargetFromUrl($objectUrl);

        if (! $target) {
            if (config('logging.dev_log')) {
                Log::warning('Could not find local target for Follow activity', [
                    'activity_id' => $this->activity['id'] ?? null,
                    'object' => $objectUrl,
                ]);
            }

            return;
        }

        // Dispatch to the regular inbox processor with the target
        ProcessInboxActivity::dispatch($this->activity, $this->actor, $target)->onQueue('activitypub-in');

        if (config('logging.dev_log')) {
            Log::info('Dispatched Follow activity to local target', [
                'activity_id' => $this->activity['id'] ?? null,
                'target' => $target->username,
                'actor' => $this->actor?->username,
            ]);
        }
    }

    /**
     * Find a local profile from a URL
     */
    protected function findLocalTargetFromUrl(string $url): ?Profile
    {
        $profileMatch = app(SanitizeService::class)->matchUrlTemplate(
            url: $url,
            templates: [
                '/ap/users/{profileId}',
                '/@{username}',
                '/users/{username}',
            ],
            useAppHost: true,
            constraints: ['profileId' => '\d+', 'username' => '[a-zA-Z0-9_.-]+']
        );

        if ($profileMatch) {
            if (isset($profileMatch['profileId'])) {
                return Profile::whereLocal(true)->find($profileMatch['profileId']);
            }

            if (isset($profileMatch['username'])) {
                return Profile::where('username', $profileMatch['username'])->whereLocal(true)->first();
            }
        }

        return Profile::where('uri', $url)->whereLocal(true)->first();
    }

    /**
     * Handle Delete activities without requiring to/cc fields
     */
    protected function handleDeleteActivity()
    {
        $validator = app(DeleteValidator::class);

        try {
            $validator->validate($this->activity);
        } catch (\Exception $e) {

            if (config('logging.dev_log')) {
                Log::warning("Delete activity failed validation: {$e->getMessage()}", [
                    'activity_id' => $this->activity['id'] ?? null,
                    'raw_activity' => $this->activity,
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

    protected function handleRelayActivity($relay)
    {
        $type = $this->activity['type'] ?? null;

        if ($type === 'Accept' && isset($this->activity['object']['type']) && $this->activity['object']['type'] === 'Follow') {
            app(\App\Services\RelayService::class)->handleAccept($relay, $this->activity);
            if (config('logging.dev_log')) {
                Log::info('Relay subscription accepted', ['relay' => $relay->relay_url]);
            }

            return;
        }

        if ($type === 'Reject' && isset($this->activity['object']['type']) && $this->activity['object']['type'] === 'Follow') {
            app(\App\Services\RelayService::class)->handleReject($relay);
            if (config('logging.dev_log')) {
                Log::warning('Relay subscription rejected', ['relay' => $relay->relay_url]);
            }

            return;
        }

        if (in_array($type, ['Create', 'Announce', 'Update'])) {
            $recipients = $this->extractRecipients();
            $localTargets = $this->findLocalTargets($recipients);

            if ($localTargets->isNotEmpty()) {
                $chunkSize = config('loops.federation.inbox_dispatch_chunk_size', 100);
                $localTargets->chunk($chunkSize)->each(function ($chunk) {
                    foreach ($chunk as $target) {
                        ProcessInboxActivity::dispatch($this->activity, $this->actor, $target)
                            ->onQueue('activitypub-in');
                    }
                });
            }

            $relay->incrementReceived();

            if (config('logging.dev_log')) {
                Log::info('Processed relay activity', [
                    'relay' => $relay->relay_url,
                    'type' => $type,
                    'targets' => $localTargets->count() ?? 0,
                ]);
            }
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
