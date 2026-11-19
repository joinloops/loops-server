<?php

namespace App\Federation\Validators;

use App\Federation\Migration;

class MigrationResponseValidator extends BaseValidator
{
    public function validate(array $activity): void
    {
        if (! $this->hasRequiredFields($activity, ['id', 'type', 'actor', 'object']) || ! Migration::isMigrationResponse($activity)) {
            throw new \Exception('Invalid migration response.');
        }

        $object = $activity['object'];

        if (! is_string($activity['actor']) || ! is_string($object['id'] ?? null) || ! is_string($object['object'] ?? null) || ! is_string($object['actor'] ?? null)) {
            throw new \Exception('Migration response is missing the original offer.');
        }

        if (! $this->isLocalObject($object['object']) || ! $this->isLocalObject($object['actor'])) {
            throw new \Exception('Migration response must reference a local migration.');
        }
    }
}
