<?php

namespace App\Federation\Validators;

use App\Federation\Migration;

class OfferValidator extends BaseValidator
{
    public function validate(array $activity): void
    {
        if (! $this->hasRequiredFields($activity, ['id', 'type', 'actor', 'object']) || $activity['type'] !== 'Offer') {
            throw new \Exception('Invalid Offer activity.');
        }

        if (! is_string($activity['actor']) || ! is_string($activity['id'])) {
            throw new \Exception('Offer actor and id must be strings.');
        }

        $object = $activity['object'];

        if (! is_array($object) || ($object['type'] ?? null) !== Migration::OBJECT_TYPE) {
            throw new \Exception('Only account migration offers are supported.');
        }

        foreach (['id', 'attributedTo', 'target', 'intent', 'manifest'] as $field) {
            if (! is_string($object[$field] ?? null)) {
                throw new \Exception("Account migration offer is missing {$field}.");
            }
        }

        if (strlen($object['intent']) > 65536) {
            throw new \Exception('Account migration intent is too large.');
        }

        if ($object['attributedTo'] !== $activity['actor']) {
            throw new \Exception('Account migration offer must be attributed to the actor.');
        }

        $actorHost = Migration::host($activity['actor']);

        foreach ([$activity['id'], $object['id'], $object['manifest']] as $url) {
            if (! str_starts_with($url, 'https://') || Migration::host($url) !== $actorHost) {
                throw new \Exception('Account migration URLs must belong to the actor server.');
            }
        }

        if (! $this->isLocalObject($object['target'])) {
            throw new \Exception('Account migration target must be a local actor.');
        }
    }
}
