<?php

namespace App\Federation\Validators;

class MoveValidator extends BaseValidator
{
    public function validate(array $activity): void
    {
        if (! $this->hasRequiredFields($activity, ['id', 'type', 'actor', 'object', 'target']) || $activity['type'] !== 'Move') {
            throw new \Exception('Invalid Move activity.');
        }

        $object = is_array($activity['object']) ? ($activity['object']['id'] ?? null) : $activity['object'];
        $target = is_array($activity['target']) ? ($activity['target']['id'] ?? null) : $activity['target'];

        if (! is_string($activity['actor']) || ! is_string($object) || ! is_string($target)) {
            throw new \Exception('Move actor, object and target must be URIs.');
        }

        if ($activity['actor'] !== $object) {
            throw new \Exception('Move must be sent by the account that moved.');
        }

        if ($object === $target || ! str_starts_with($target, 'https://')) {
            throw new \Exception('Invalid Move target.');
        }
    }
}
