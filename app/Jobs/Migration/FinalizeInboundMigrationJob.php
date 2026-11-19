<?php

namespace App\Jobs\Migration;

use App\Models\AccountMigration;
use App\Services\Migration\InboundMigrationService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class FinalizeInboundMigrationJob implements ShouldBeUnique, ShouldQueue
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
        return 'migration-finalize-in-'.$this->migrationId;
    }

    public function backoff(): array
    {
        return [60, 300, 900];
    }

    public function handle(InboundMigrationService $service): void
    {
        $migration = AccountMigration::find($this->migrationId);

        if (! $migration || ! $migration->isInbound()) {
            return;
        }

        $service->finalize($migration);
    }

    public function failed(\Throwable $exception): void
    {
        $migration = AccountMigration::find($this->migrationId);

        if ($migration && $migration->isActive()) {
            $migration->fail('Sealing the migration failed: '.$exception->getMessage());
        }
    }
}
