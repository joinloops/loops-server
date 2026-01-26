<?php

namespace App\Federation\Validators;

use App\Services\SanitizeService;

class DeleteValidator extends BaseValidator
{
    /**
     * Validates an incoming Delete activity.
     *
     * @throws \Exception if the activity is invalid.
     */
    public function validate(array $activity): void
    {
        if (! $this->hasRequiredFields($activity, ['type', 'actor', 'object'])) {
            throw new \Exception('Delete activity is missing required fields: type, actor, or object.');
        }

        if ($activity['type'] !== 'Delete') {
            throw new \Exception("Invalid activity type: expected 'Delete', got '{$activity['type']}'.");
        }

        if (! is_string($activity['actor'])) {
            throw new \Exception('Delete activity "actor" must be a string URI.');
        }

        if (is_array($activity['object']) && isset($activity['object']['type'])) {
            $allowedTypes = ['Person', 'Tombstone'];
            if (! in_array($activity['object']['type'], $allowedTypes)) {
                throw new \Exception("Delete activity 'object' has an invalid embedded type: '{$activity['object']['type']}'.");
            }
        }

        $objectUrl = is_array($activity['object']) ? $activity['object']['id'] ?? null : $activity['object'];

        if (! $objectUrl || ! is_string($objectUrl)) {
            throw new \Exception('Delete activity "object" must be a string URI or an object with an "id" property.');
        }

        if (! app(SanitizeService::class)->url($activity['actor'], true)) {
            throw new \Exception('Delete activity "actor" URI is invalid.');
        }

        if (! app(SanitizeService::class)->url($objectUrl, true)) {
            throw new \Exception('Delete activity "object" URI is invalid.');
        }

        $isOwner = ($objectUrl === $activity['actor']) || str_starts_with($objectUrl, $activity['actor'] . '/');
        
        if (! $isOwner) {
            throw new \Exception("Delete activity actor '{$activity['actor']}' does not appear to be the owner of the object '{$objectUrl}'.");
        }

        if ($this->isLocalObject($objectUrl)) {
            throw new \Exception("Delete activity rejected: a remote actor cannot delete a local object '{$objectUrl}'.");
        }
    }
}
