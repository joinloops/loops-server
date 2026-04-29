<?php

use App\Console\Commands\SnapshotUsage;
use Illuminate\Support\Facades\Schedule;

Schedule::command(SnapshotUsage::class)->dailyAt('00:05')->timezone('UTC')->onOneServer();
Schedule::command('app:expire-user-register-verifications')->everyFiveMinutes()->onOneServer();
Schedule::command('horizon:snapshot')->hourly()->onOneServer();
Schedule::command('instances:update-stats --create-missing')->daily()->at('04:20')->onOneServer();
Schedule::command('feed:update-interests')->hourly()->onOneServer();
Schedule::command('app:system-message-seeder')->daily()->onOneServer();
Schedule::command('app:system-message-garbage-collector')->daily()->onOneServer();
Schedule::command('passport:purge --revoked')->daily()->onOneServer();
Schedule::command('version:check --force')->twiceDaily(9, 17)->withoutOverlapping()->runInBackground()->onOneServer();
Schedule::command('app:instance-stats-collector-command')->hourlyAt(20)->onOneServer();
Schedule::command('logs:clean-admin-security --days=14')->dailyAt('03:00')->onOneServer();
Schedule::command('curated:expire-stale')->hourly()->onOneServer();
Schedule::command('admin:check-activity')->everySixHours(15)->onOneServer();
Schedule::command('app:purge-old-activities')->daily()->at('16:20')->onOneServer();

if (config('loops.admin_dashboard.autoUpdate')) {
    Schedule::command('admin:refresh-dashboard-30d')->everyThirtyMinutes()->onOneServer();
}

if (config('loops.backups.enabled')) {
    Schedule::command('backup:clean')->daily()->at('01:00')->onOneServer();
    Schedule::command('backup:run --only-db')->everySixHours()->onOneServer();
}
