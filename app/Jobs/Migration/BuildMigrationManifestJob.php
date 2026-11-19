<?php

namespace App\Jobs\Migration;

use App\Models\AccountMigration;
use App\Services\Migration\ManifestBuilder;
use App\Services\Migration\OutboundMigrationService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class BuildMigrationManifestJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public $timeout;

    public $maxExceptions = 2;

    public $uniqueFor = 3600;

    public function __construct(public int $migrationId)
    {
        $this->timeout = max(300, (int) config('loops.migration.job_timeout', 900));
        $this->onConnection('redis-migration');
        $this->onQueue('migration');
    }

    public function uniqueId(): string
    {
        return 'migration-manifest-'.$this->migrationId;
    }

    public function retryUntil(): \DateTimeInterface
    {
        return now()->addHours(12);
    }

    public function handle(ManifestBuilder $builder, OutboundMigrationService $service): void
    {
        $migration = AccountMigration::find($this->migrationId);

        if (! $migration || $migration->state !== AccountMigration::STATE_BUILDING) {
            return;
        }

        if (! $builder->backfillHashes($migration, microtime(true) + max(60, $this->timeout - 120))) {
            $this->release(5);

            return;
        }

        $builder->build($migration);

        $migration->refresh();

        if ($migration->state !== AccountMigration::STATE_BUILDING) {
            return;
        }

        $service->offer($migration);
    }

    public function failed(\Throwable $exception): void
    {
        AccountMigration::find($this->migrationId)?->fail('Preparing the migration failed: '.$exception->getMessage());
    }
}
