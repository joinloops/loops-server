<?php

namespace App\Jobs\Migration;

use App\Models\AccountMigration;
use App\Services\Migration\OutboundMigrationService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class FinalizeOutboundMigrationJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public $tries = 3;

    public $timeout = 600;

    public $uniqueFor = 3600;

    public function __construct(public int $migrationId)
    {
        $this->onConnection('redis-migration');
        $this->onQueue('migration');
    }

    public function uniqueId(): string
    {
        return 'migration-finalize-out-'.$this->migrationId;
    }

    public function backoff(): array
    {
        return [60, 300];
    }

    public function handle(OutboundMigrationService $service): void
    {
        $migration = AccountMigration::find($this->migrationId);

        if (! $migration || ! $migration->isOutbound()) {
            return;
        }

        $service->finalize($migration);
    }
}
