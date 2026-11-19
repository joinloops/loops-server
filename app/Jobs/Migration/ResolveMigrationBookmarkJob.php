<?php

namespace App\Jobs\Migration;

use App\Models\AccountMigration;
use App\Models\AccountMigrationItem;
use App\Services\Migration\InboundMigrationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ResolveMigrationBookmarkJob implements ShouldQueue
{
    use Queueable;

    public $tries = 5;

    public $timeout = 120;

    public function __construct(public int $migrationId, public int $itemId)
    {
        $this->onConnection('redis-migration');
        $this->onQueue('migration');
    }

    public function handle(InboundMigrationService $service): void
    {
        $migration = AccountMigration::find($this->migrationId);
        $item = AccountMigrationItem::where('migration_id', $this->migrationId)->find($this->itemId);

        if (! $migration || ! $item || ! $migration->profile) {
            return;
        }

        if (! $service->resolveBookmark($migration, $item, $this->attempts())) {
            $this->release(180);

            return;
        }

        $migration->refreshCounters();
    }

    public function failed(\Throwable $exception): void
    {
        $item = AccountMigrationItem::find($this->itemId);

        if ($item && $item->state === AccountMigrationItem::STATE_PENDING) {
            $item->markSkipped('Video unavailable');
        }
    }
}
