<?php

namespace App\Jobs\Migration;

use App\Models\AccountMigration;
use App\Services\Migration\OutboundMigrationService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class MoveLocalFollowersJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public $tries = 2;

    public $timeout = 900;

    public $uniqueFor = 3600;

    public function __construct(public int $migrationId)
    {
        $this->onConnection('redis-migration');
        $this->onQueue('migration');
    }

    public function uniqueId(): string
    {
        return 'migration-local-followers-'.$this->migrationId;
    }

    public function handle(OutboundMigrationService $service): void
    {
        $migration = AccountMigration::find($this->migrationId);

        if (! $migration || $migration->state !== AccountMigration::STATE_FINALIZED) {
            return;
        }

        $service->moveLocalFollowers($migration);
    }
}
