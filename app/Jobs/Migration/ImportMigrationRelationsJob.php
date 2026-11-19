<?php

namespace App\Jobs\Migration;

use App\Models\AccountMigration;
use App\Services\Migration\InboundMigrationService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ImportMigrationRelationsJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public $tries = 3;

    public $timeout = 900;

    public $uniqueFor = 3600;

    public function __construct(public int $migrationId)
    {
        $this->onConnection('redis-migration');
        $this->onQueue('migration');
    }

    public function uniqueId(): string
    {
        return 'migration-relations-'.$this->migrationId;
    }

    public function backoff(): array
    {
        return [60, 300];
    }

    public function handle(InboundMigrationService $service): void
    {
        $migration = AccountMigration::find($this->migrationId);

        if (! $migration || ! $migration->isInbound()) {
            return;
        }

        $service->importRelations($migration);
    }

    public function failed(\Throwable $exception): void
    {
        FinalizeInboundMigrationJob::dispatch($this->migrationId);
    }
}
