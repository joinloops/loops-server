<?php

namespace App\Jobs\Migration;

use App\Models\AccountMigration;
use App\Services\Migration\OutboundMigrationService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class FetchMigrationMapJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public $tries = 4;

    public $timeout = 600;

    public $uniqueFor = 3600;

    public function __construct(public int $migrationId)
    {
        $this->onConnection('redis-migration');
        $this->onQueue('migration');
    }

    public function uniqueId(): string
    {
        return 'migration-map-'.$this->migrationId;
    }

    public function backoff(): array
    {
        return [60, 300, 900];
    }

    public function handle(OutboundMigrationService $service): void
    {
        $migration = AccountMigration::find($this->migrationId);

        if (! $migration || $migration->state !== AccountMigration::STATE_TRANSFERRING) {
            return;
        }

        $service->applyMap($migration);
    }

    public function failed(\Throwable $exception): void
    {
        AccountMigration::find($this->migrationId)?->fail('Fetching the object map failed: '.$exception->getMessage());
    }
}
