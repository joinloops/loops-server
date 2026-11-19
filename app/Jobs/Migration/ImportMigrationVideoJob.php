<?php

namespace App\Jobs\Migration;

use App\Models\AccountMigration;
use App\Models\AccountMigrationItem;
use App\Services\Migration\InboundMigrationService;
use App\Services\Migration\MigrationException;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;

class ImportMigrationVideoJob implements ShouldQueue
{
    use Batchable, Queueable;

    public $tries = 3;

    public $timeout;

    public $deleteWhenMissingModels = true;

    public function __construct(public int $migrationId, public int $itemId)
    {
        $this->timeout = max(300, (int) config('loops.migration.job_timeout', 900));
        $this->onConnection('redis-migration');
        $this->onQueue('migration');
    }

    public function middleware(): array
    {
        return [
            (new WithoutOverlapping('migration-item:'.$this->itemId))->releaseAfter(60)->expireAfter($this->timeout + 60),
        ];
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
        $item = AccountMigrationItem::where('migration_id', $this->migrationId)->find($this->itemId);

        if (! $migration || ! $item || $migration->state !== AccountMigration::STATE_TRANSFERRING) {
            return;
        }

        $item->increment('attempts');

        try {
            $service->importVideo($migration, $item);
        } catch (MigrationException $e) {
            if (in_array($e->reason, ['hash_mismatch', 'manifest_mismatch', 'over_limits', 'invalid_media', 'cancelled'], true)) {
                $item->markFailed($e->reason.': '.$e->getMessage());

                return;
            }

            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        $item = AccountMigrationItem::find($this->itemId);

        if ($item && $item->state === AccountMigrationItem::STATE_PENDING) {
            $item->markFailed($exception->getMessage());
        }
    }
}
