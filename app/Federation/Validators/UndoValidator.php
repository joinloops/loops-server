<?php

namespace App\Federation\Validators;

use App\Models\Profile;
use App\Services\SanitizeService;

class UndoValidator extends BaseValidator
{
    /**
     * Validates an incoming Undo activity.
     *
     * @throws \Exception if the activity is invalid.
     */
    public function validate(array $activity): void
    {
        if (! $this->hasRequiredFields($activity, ['type', 'actor', 'object'])) {
            throw new \Exception('Undo activity is missing required fields: type, actor, or object.');
        }

        if ($activity['type'] !== 'Undo') {
            throw new \Exception("Invalid activity type: expected 'Undo', got '{$activity['type']}'.");
        }

        if (! is_string($activity['actor'])) {
            throw new \Exception('Undo activity "actor" must be a string URI.');
        }

        if (! is_array($activity['object'])) {
            throw new \Exception('Undo activity "object" must be an array (the original activity).');
        }

        if (! app(SanitizeService::class)->url($activity['actor'], true)) {
            throw new \Exception('Undo activity "actor" URI is invalid.');
        }

        $object = $activity['object'];

        // Validate the structure of the original activity
        if (empty($object['type']) || ! is_string($object['type'])) {
            throw new \Exception('Original activity (the "object") must have a "type" property.');
        }

        if (empty($object['object']) || ! is_string($object['object'])) {
            throw new \Exception('Original activity (the "object") must have its own "object" property (the target URI).');
        }

        // The actor of the Undo must be the same as the actor of the original activity
        if (!isset($object['actor'])) {
            throw new \Exception('Original activity must have an "actor" field.');
        }
        
        if ($object['actor'] !== $activity['actor']) {
            throw new \Exception('Undo activity actor does not match the original activity actor.');
        }

        // Validate the target of the original activity is a URL
        if (! app(SanitizeService::class)->url($object['object'], true)) {
            throw new \Exception('The "object" of the original activity is not a valid URL.');
        }

        // Dispatch based on the *original* activity's type
        match ($object['type']) {
            'Follow' => $this->validateUndoFollow($object),
            'Like' => $this->validateUndoLike($object),
            'Announce' => $this->validateUndoAnnounce($object),
            default => $this->validateGenericUndo($object)
        };
    }

    /**
     * @throws \Exception
     */
    private function validateUndoFollow(array $followObject): void
    {
        if (empty($followObject['object']) || ! is_string($followObject['object'])) {
            throw new \Exception('Undo Follow "object" (the original activity) is missing its own "object" (the profile URI).');
        }

        // We only process unfollows if the target profile is one of ours.
        if (! $this->isLocalProfile($followObject['object'])) {
            throw new \Exception("Undo Follow rejected: target profile '{$followObject['object']}' is not a local profile.");
        }
    }

    /**
     * @throws \Exception
     */
    private function validateUndoLike(array $likeObject): void
    {
        if (empty($likeObject['object']) || ! is_string($likeObject['object'])) {
            throw new \Exception('Undo Like "object" (the original activity) is missing its own "object" (the status URI).');
        }
    }

    /**
     * @throws \Exception
     */
    private function validateUndoAnnounce(array $announceObject): void
    {
        if (empty($announceObject['object']) || ! is_string($announceObject['object'])) {
            throw new \Exception('Undo Announce "object" (the original activity) is missing its own "object" (the status URI).');
        }
    }

    /**
     * @throws \Exception
     */
    private function validateGenericUndo(array $object): void
    {
        throw new \Exception("Unsupported object type for Undo activity: '{$object['type']}'.");
    }
}
