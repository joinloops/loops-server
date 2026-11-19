<?php

namespace App\Jobs\Migration;

use App\Models\AccountMigration;
use App\Services\Migration\InboundMigrationService;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ImportMigrationGraphJob implements ShouldQueue
{
    use Batchable, Queueable;

    public $tries = 3;

    public $timeout = 600;

    public function __construct(
        public int $migrationId,
        public string $kind,
        public array $itemIds,
        public int $chunkIndex = 0,
    ) {
        $this->onConnection('redis-migration');
        $this->onQueue('migration');
    }

    public function backoff(): array
    {
        return [60, 300];
    }

    public function handle(InboundMigrationService $service): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }

        $migration = AccountMigration::find($this->migrationId);

        if (! $migration || ! $migration->isInbound()) {
            return;
        }

        $service->importGraph($migration, $this->kind, $this->itemIds, $this->chunkIndex);
    }
}
