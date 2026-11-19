<?php

namespace App\Federation\Handlers;

use App\Models\Profile;
use App\Services\Migration\InboundMigrationService;

class OfferHandler extends BaseHandler
{
    public function handle(array $activity, Profile $actor, ?Profile $target = null)
    {
        $targetUri = $activity['object']['target'];

        if (! $this->isLocalObject($targetUri)) {
            return;
        }

        $local = app(Profile::class)->findOrCreateFromUrl($targetUri);

        if (! $local || ! $local->local || ($target && (string) $target->id !== (string) $local->id)) {
            return;
        }

        app(InboundMigrationService::class)->handleOffer($activity, $actor, $local);
    }
}
