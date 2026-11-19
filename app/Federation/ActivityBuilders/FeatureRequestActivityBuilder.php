<?php

namespace App\Federation\ActivityBuilders;

use App\Models\Profile;
use App\Models\StarterKitAccount;

class FeatureRequestActivityBuilder
{
    /**
     * Build an Accept activity in response to an incoming FeatureRequest.
     *
     * @param  Profile  $actor  Local profile (the user being featured / responding)
     * @param  Profile  $recipient  Remote profile (the kit owner who sent the request)
     * @param  StarterKitAccount  $account  The pivot row for this acceptance
     * @param  string  $featureRequestId  The id of the original FeatureRequest activity
     */
    public static function buildAccept(
        Profile $actor,
        Profile $recipient,
        StarterKitAccount $account,
        string $featureRequestId,
    ): array {
        return [
            '@context' => ['https://www.w3.org/ns/activitystreams'],
            'id' => $account->getAttestationUrl('/accept'),
            'type' => 'Accept',
            'actor' => $actor->getActorId(),
            'to' => $recipient->getActorId(),
            'object' => $featureRequestId,
            'result' => $account->getAttestationUrl(),
        ];
    }

    /**
     * Build a Reject activity in response to an incoming FeatureRequest.
     */
    public static function buildReject(
        Profile $actor,
        Profile $recipient,
        StarterKitAccount $account,
        string $featureRequestId,
    ): array {
        return [
            '@context' => ['https://www.w3.org/ns/activitystreams'],
            'id' => $account->getAttestationUrl('/reject'),
            'type' => 'Reject',
            'actor' => $actor->getActorId(),
            'to' => $recipient->getActorId(),
            'object' => $featureRequestId,
        ];
    }

    /**
     * Build a Delete activity that revokes a previously-issued FeatureAuthorization.
     *
     * @param  Profile  $actor  Local profile (the user revoking their feature attestation)
     * @param  Profile  $recipient  Remote profile (the kit owner)
     * @param  StarterKitAccount  $account  The pivot row being revoked
     */
    public static function buildRevoke(
        Profile $actor,
        Profile $recipient,
        StarterKitAccount $account,
    ): array {
        return [
            '@context' => ['https://www.w3.org/ns/activitystreams'],
            'id' => $account->getAttestationUrl('/revoke'),
            'type' => 'Delete',
            'actor' => $actor->getActorId(),
            'to' => $recipient->getActorId(),
            'object' => [
                'id' => $account->getAttestationUrl(),
                'type' => 'FeatureAuthorization',
                'interactingObject' => $account->starterKit->getPermalink(),
                'interactionTarget' => $actor->getActorId(),
            ],
        ];
    }
}
