<?php

namespace App\Services;

use App\Federation\Migration;
use App\Models\Activity;
use App\Models\InstanceActor;
use App\Models\Profile;
use Illuminate\Support\Arr;

class ActivityService
{
    /**
     * Process an incoming activity
     */
    public function processIncomingActivity(
        array $activityData,
        Profile|InstanceActor $actor,
        ?Profile $target = null
    ) {
        $type = $activityData['type'] ?? null;

        if (is_string($type) && Migration::isMigrationResponse($activityData)) {
            $type = 'MigrationResponse';
        }

        $mapping = $this->getMapType($type);

        if (! $mapping) {
            return;
        }

        $activity = $actor instanceof Profile
            ? $this->storeActivity($activityData, $actor)
            : null;

        if (isset($mapping['validator'])) {
            $validator = app($mapping['validator']);

            try {
                $validator->validate($activityData);
            } catch (\Exception $e) {
                $activity?->markAsProcessed();

                return;
            }
        }

        $activityData = $this->sanitizeActivityContent($activityData);

        try {
            $handler = app($mapping['handler']);

            $result = $handler->handle(
                $activityData,
                $actor,
                $target
            );

            $activity?->markAsProcessed();

            return $result;
        } catch (\Throwable $e) {
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
            'FeatureRequest' => [
                'handler' => \App\Federation\Handlers\FeatureRequestHandler::class,
                'validator' => \App\Federation\Validators\FeatureRequestValidator::class,
            ],
            'Remove' => [
                'handler' => \App\Federation\Handlers\RemoveHandler::class,
                'validator' => \App\Federation\Validators\RemoveValidator::class,
            ],
            'Block' => [
                'handler' => \App\Federation\Handlers\BlockHandler::class,
                'validator' => \App\Federation\Validators\BlockValidator::class,
            ],
            'Offer' => [
                'handler' => \App\Federation\Handlers\OfferHandler::class,
                'validator' => \App\Federation\Validators\OfferValidator::class,
            ],
            'Move' => [
                'handler' => \App\Federation\Handlers\MoveHandler::class,
                'validator' => \App\Federation\Validators\MoveValidator::class,
            ],
            'MigrationResponse' => [
                'handler' => \App\Federation\Handlers\MigrationResponseHandler::class,
                'validator' => \App\Federation\Validators\MigrationResponseValidator::class,
            ],
        ];
    }

    public function getMapType($type)
    {
        $mapping = $this->activityMap();

        if (isset($mapping[$type])) {
            return $mapping[$type];
        }

        return false;
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

    protected function extractRawActivity(array $activity): ?array
    {
        Arr::forget($activity, '@context');
        Arr::forget($activity, 'to');
        Arr::forget($activity, 'cc');
        Arr::forget($activity, 'bcc');
        Arr::forget($activity, 'contentMap');
        Arr::forget($activity, 'atomUri');

        return $activity;
    }

    /**
     * Store an activity in the database
     */
    protected function storeActivity(
        array $activityData,
        Profile $actor
    ): Activity {
        return Activity::firstOrCreate(
            [
                'activity_id' => $activityData['id'],
            ],
            [
                'type' => $activityData['type'] ?? 'Unknown',
                'profile_id' => $actor->id,
                'to' => $activityData['to'] ?? null,
                'cc' => $activityData['cc'] ?? null,
                'bcc' => $activityData['bcc'] ?? null,
                'payload' => $this->extractContent($activityData),
                'raw_activity' => $this->extractRawActivity($activityData),
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

        } catch (\Exception $e) {
            return null;
        }

        return null;
    }

    /**
     * Fetch a remote actor
     */
    public function fetchRemoteActivity(string $url): ?array
    {
        try {
            $response = app(ActivityPubService::class)->get($url);

            if ($response) {
                return $response;
            }
        } catch (\Exception $e) {
            return null;
        }

        return null;
    }
}
