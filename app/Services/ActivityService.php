<?php

namespace App\Services;

use App\Models\Activity;
use App\Models\Profile;
use App\Models\InstanceActor;
use Illuminate\Support\Facades\Log;

class ActivityService
{
    /**
     * Process an incoming activity
     */
    public function processIncomingActivity(array $activityData, Profile|InstanceActor $actor, Profile $target)
    {
        $type = $activityData['type'] ?? null;
        $mapping = $this->getMapType($type);

        if (! $mapping) {
            if (config('logging.dev_log')) {
                Log::warning("Unknown activity type: {$type}", [
                    'actor' => $actor->uri,
                    'activity' => $activityData['id'] ?? null,
                ]);
            }
            throw new \Exception("Unknown activity type: {$type}");
        }

        $activity = $this->storeActivity($activityData, $actor, $target);

        if (isset($mapping['validator'])) {
            $validator = app($mapping['validator']);
            try {
                $validator->validate($activityData);
            } catch (\Exception $e) {
                $activity->markAsProcessed();

                if (config('logging.dev_log')) {
                    Log::warning("Activity validation failed: {$e->getMessage()}", [
                        'actor' => $actor->uri,
                        'activity_id' => $activityData['id'] ?? null,
                        'raw_activity' => $activityData,
                    ]);
                }

                return;
            }
        }

        $activityData = $this->sanitizeActivityContent($activityData);

        try {
            $handler = app($mapping['handler']);
            $result = $handler->handle($activityData, $actor, $target);

            $activity->markAsProcessed();

            return $result;
        } catch (\Exception $e) {
            if (config('logging.dev_log')) {
                Log::error('Failed to process activity', [
                    'type' => $type,
                    'actor' => $actor->uri,
                    'error' => $e->getMessage(),
                ]);
            }
            throw $e;
        }
    }

    public function activityMap()
    {
        return [
            'Accept' => [
                'handler' => \App\Federation\Handlers\AcceptHandler::class,
                'validator' => \App\Federation\Validators\AcceptValidator::class,
            ],
            'Create' => [
                'handler' => \App\Federation\Handlers\CreateHandler::class,
                'validator' => \App\Federation\Validators\CreateValidator::class,
            ],
            'Follow' => [
                'handler' => \App\Federation\Handlers\FollowHandler::class,
                'validator' => \App\Federation\Validators\FollowValidator::class,
            ],
            'Like' => [
                'handler' => \App\Federation\Handlers\LikeHandler::class,
                'validator' => \App\Federation\Validators\LikeValidator::class,
            ],
            'Announce' => [
                'handler' => \App\Federation\Handlers\AnnounceHandler::class,
                'validator' => \App\Federation\Validators\AnnounceValidator::class,
            ],
            'Undo' => [
                'handler' => \App\Federation\Handlers\UndoHandler::class,
                'validator' => \App\Federation\Validators\UndoValidator::class,
            ],
            'Delete' => [
                'handler' => \App\Federation\Handlers\DeleteHandler::class,
                'validator' => \App\Federation\Validators\DeleteValidator::class,
            ],
            'Flag' => [
                'handler' => \App\Federation\Handlers\FlagHandler::class,
                'validator' => \App\Federation\Validators\FlagValidator::class,
            ],
            'Update' => [
                'handler' => \App\Federation\Handlers\UpdateHandler::class,
                'validator' => \App\Federation\Validators\UpdateValidator::class,
            ],
            'QuoteRequest' => [
                'handler' => \App\Federation\Handlers\QuoteRequestHandler::class,
                'validator' => \App\Federation\Validators\QuoteRequestValidator::class,
            ],
        ];
    }

    public function getMapType($type)
    {
        $mapping = $this->activityMap();

        if (isset($mapping[$type])) {
            return $mapping[$type];
        }
    }

    /**
     * Sanitize activity content
     */
    protected function sanitizeActivityContent(array $activity): array
    {
        if (isset($activity['content'])) {
            $activity['content'] = app(SanitizeService::class)->cleanHtmlWithSpacing($activity['content']);
        }

        if (isset($activity['object']['content'])) {
            $activity['object']['content'] = app(SanitizeService::class)->cleanHtmlWithSpacing(
                $activity['object']['content']
            );
        }

        return $activity;
    }

    /**
     * Extract content from activity
     */
    protected function extractContent(array $activity): ?string
    {
        if (isset($activity['content'])) {
            return $activity['content'];
        }

        if (isset($activity['object']['content'])) {
            return $activity['object']['content'];
        }

        if (isset($activity['object']) && is_string($activity['object'])) {
            return $activity['object'];
        }

        return null;
    }

    /**
     * Store an activity in the database
     */
    protected function storeActivity(array $activityData, Profile|InstanceActor $actor, Profile $target): Activity
    {
        return Activity::firstOrCreate(
            ['activity_id' => $activityData['id']],
            [
                'type' => $activityData['type'] ?? 'Unknown',
                'profile_id' => $actor->id,
                'to' => $activityData['to'] ?? [],
                'cc' => $activityData['cc'] ?? [],
                'bcc' => $activityData['bcc'] ?? [],
                'payload' => $this->extractContent($activityData),
                'raw_activity' => $activityData,
                'processed' => false,
            ]
        );
    }

    /**
     * Fetch a remote actor
     */
    public function fetchRemoteActor(string $url): ?array
    {
        try {
            $response = app(ActivityPubService::class)->get($url);

            if ($response) {
                return $response;
            }

            if (config('logging.dev_log')) {
                Log::warning('Failed to fetch remote actor', [
                    'url' => $url,
                ]);
            }
        } catch (\Exception $e) {
            if (config('logging.dev_log')) {
                Log::error('Exception fetching remote actor', [
                    'url' => $url,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return null;
    }
}
