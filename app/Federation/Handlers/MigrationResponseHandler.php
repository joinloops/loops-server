<?php

namespace App\Federation\Handlers;

use App\Federation\Migration;
use App\Models\AccountMigration;
use App\Models\Profile;
use App\Services\Migration\OutboundMigrationService;
use App\Services\SanitizeService;

class MigrationResponseHandler extends BaseHandler
{
    public function handle(array $activity, Profile $actor, ?Profile $target = null)
    {
        $object = $activity['object'];

        $match = app(SanitizeService::class)->matchUrlTemplate(
            url: $object['object'],
            templates: ['/ap/migrations/{uuid}'],
            useAppHost: true,
            constraints: ['uuid' => '[0-9a-fA-F-]{36}']
        );

        if (! $match) {
            return;
        }

        $migration = AccountMigration::outbound()->where('uuid', $match['uuid'])->first();

        if (! $migration || $migration->remote_actor !== $actor->uri || $object['actor'] !== $migration->local_actor || Migration::host($actor->uri) !== $migration->remote_domain) {
            return;
        }

        $service = app(OutboundMigrationService::class);

        if ($activity['type'] === 'Accept') {
            $service->handleAccept($migration, $activity);

            return;
        }

        $service->handleReject($migration, $activity);
    }
}
