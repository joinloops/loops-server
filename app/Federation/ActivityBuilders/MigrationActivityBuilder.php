<?php

namespace App\Federation\ActivityBuilders;

use App\Federation\Migration;
use App\Models\AccountMigration;
use App\Models\Profile;

class MigrationActivityBuilder
{
    public static function offer(AccountMigration $migration, Profile $actor): array
    {
        return [
            '@context' => Migration::context(),
            'id' => $migration->offerId(),
            'type' => 'Offer',
            'actor' => $actor->getActorId(),
            'to' => [$migration->remote_actor],
            'target' => $migration->remote_actor,
            'published' => now()->toIso8601ZuluString(),
            'object' => [
                'id' => $migration->apUrl(),
                'type' => Migration::OBJECT_TYPE,
                'attributedTo' => $actor->getActorId(),
                'target' => $migration->remote_actor,
                'intent' => $migration->intent_jws,
                'manifest' => $migration->manifestUrl(),
                'transit' => $migration->transit_url,
            ],
        ];
    }

    public static function offerReference(string $offerId, string $sourceActor, string $migrationId): array
    {
        return [
            'id' => $offerId,
            'type' => 'Offer',
            'actor' => $sourceActor,
            'object' => $migrationId,
        ];
    }

    public static function accept(AccountMigration $migration, Profile $actor): array
    {
        return [
            '@context' => Migration::context(),
            'id' => $actor->getActorId('#migrations/'.$migration->uuid.'/accept'),
            'type' => 'Accept',
            'actor' => $actor->getActorId(),
            'to' => [$migration->remote_actor],
            'published' => now()->toIso8601ZuluString(),
            'object' => self::offerReference($migration->remote_migration_id.'#offer', $migration->remote_actor, $migration->remote_migration_id),
            'result' => [
                'type' => Migration::RECEIPT_TYPE,
                'receipt' => $migration->receipt_jws,
                'seal' => $migration->seal['jws'] ?? null,
                'transit' => $migration->transit_url,
            ],
        ];
    }

    public static function reject(Profile $actor, string $remoteActor, string $migrationId, string $reason): array
    {
        return [
            '@context' => Migration::context(),
            'id' => $actor->getActorId('#migrations/'.substr(hash('sha256', $migrationId), 0, 32).'/reject'),
            'type' => 'Reject',
            'actor' => $actor->getActorId(),
            'to' => [$remoteActor],
            'published' => now()->toIso8601ZuluString(),
            'object' => self::offerReference($migrationId.'#offer', $remoteActor, $migrationId),
            'summary' => $reason,
        ];
    }

    public static function move(AccountMigration $migration, Profile $actor, string $targetActor): array
    {
        return [
            '@context' => Migration::context(),
            'id' => $actor->getActorId('#moves/'.$migration->uuid),
            'type' => 'Move',
            'actor' => $actor->getActorId(),
            'object' => $actor->getActorId(),
            'target' => $targetActor,
            'to' => [$actor->getFollowersUrl()],
            'published' => now()->toIso8601ZuluString(),
            'attestation' => $migration->apUrl(),
        ];
    }
}
