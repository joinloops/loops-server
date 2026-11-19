<?php

namespace App\Jobs\Migration;

use App\Models\AccountMigration;
use App\Services\Migration\InboundMigrationService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class PlanInboundMigrationJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public $tries = 4;

    public $timeout = 900;

    public $uniqueFor = 3600;

    public function __construct(public int $migrationId)
    {
        $this->onConnection('redis-migration');
        $this->onQueue('migration');
    }

    public function uniqueId(): string
    {
        return 'migration-plan-'.$this->migrationId;
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

        $service->plan($migration);
    }

    public function failed(\Throwable $exception): void
    {
        $migration = AccountMigration::find($this->migrationId);

        if (! $migration || ! $migration->isActive()) {
            return;
        }

        $migration->fail('Reading the manifest failed: '.$exception->getMessage());

        if ($migration->profile && $migration->remoteProfile) {
            app(InboundMigrationService::class)->reject($migration->profile, $migration->remoteProfile, $migration->remote_migration_id, 'manifest_unavailable');
        }
    }
}
