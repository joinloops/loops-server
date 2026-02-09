<?php

namespace App\Federation\Validators;

use App\Services\SanitizeService;
use Carbon\Carbon;
use Carbon\Exceptions\InvalidFormatException;

class UpdateValidator extends BaseValidator
{
    /**
     * @throws \Exception
     */
    public function validate(array $activity): void
    {
        if (! $this->hasRequiredFields($activity, ['type', 'actor', 'object'])) {
            throw new \Exception('Update activity is missing required fields: type, actor, or object.');
        }

        if ($activity['type'] !== 'Update') {
            throw new \Exception("Activity type must be 'Update', found '{$activity['type']}'.");
        }

        if (! is_string($activity['actor'])) {
            throw new \Exception('Activity "actor" property must be a string (URI).');
        }

        if (! is_array($activity['object'])) {
            throw new \Exception('Activity "object" property must be an object (array).');
        }

        if (! app(SanitizeService::class)->url($activity['actor'], true)) {
            throw new \Exception('Activity "actor" URI is invalid.');
        }

        $object = $activity['object'];

        if (empty($object['type']) || ! is_string($object['type'])) {
            throw new \Exception('Activity "object" is missing a "type" string.');
        }

        if (empty($object['id']) || ! is_string($object['id'])) {
            throw new \Exception('Activity "object" is missing an "id" string (URI).');
        }

        if (! app(SanitizeService::class)->url($object['id'])) {
            throw new \Exception('Activity "object" ID is not a valid URL.');
        }

        match ($object['type']) {
            'Note', 'Article', 'Video', 'Image' => $this->validateStatusUpdate($activity, $object),
            'Person' => $this->validateProfileUpdate($activity, $object),
            default => $this->validateGenericUpdate($activity, $object)
        };
    }

    /**
     * @throws \Exception
     */
    private function validateStatusUpdate(array $activity, array $object): void
    {
        if (isset($object['content']) && ! is_string($object['content'])) {
            throw new \Exception('Object "content" must be a string.');
        }

        if (isset($object['summary']) && ! is_string($object['summary'])) {
            throw new \Exception('Object "summary" must be a string.');
        }

        if (isset($object['sensitive']) && ! is_bool($object['sensitive'])) {
            throw new \Exception('Object "sensitive" must be a boolean.');
        }

        if (isset($object['updated']) && ! is_string($object['updated'])) {
            throw new \Exception('Object "updated" timestamp must be a string.');
        }

        if (isset($object['updated'])) {
            try {
                Carbon::parse($object['updated']);
            } catch (InvalidFormatException $e) {
                throw new \Exception("Object 'updated' timestamp is invalid: {$e->getMessage()}");
            }
        }
    }

    /**
     * @throws \Exception
     */
    private function validateProfileUpdate(array $activity, array $object): void
    {
        if (isset($object['name']) && ! is_string($object['name'])) {
            throw new \Exception('Object "name" must be a string.');
        }

        if (isset($object['summary']) && ! is_string($object['summary'])) {
            throw new \Exception('Object "summary" must be a string.');
        }

        if (isset($object['icon']) && (! is_array($object['icon']) || ! isset($object['icon']['url']))) {
            throw new \Exception('Object "icon" must be an object with a "url" property.');
        }
    }

    /**
     * @throws \Exception
     */
    private function validateGenericUpdate(array $activity, array $object): void
    {
        throw new \Exception("Unsupported object type for Update: '{$object['type']}'.");
    }
}
