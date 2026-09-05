<?php

use App\Console\Commands\SnapshotUsage;
use Illuminate\Support\Facades\Schedule;

Schedule::command(SnapshotUsage::class)->dailyAt('00:05')->timezone('UTC')->runInBackground()->onOneServer();
Schedule::command('app:expire-user-register-verifications')->everyFiveMinutes()->runInBackground()->onOneServer();
Schedule::command('horizon:snapshot')->hourly()->runInBackground()->onOneServer();
Schedule::command('instances:update-stats')->daily()->at('04:20')->runInBackground()->onOneServer();
Schedule::command('feed:update-interests')->hourly()->runInBackground()->onOneServer();
Schedule::command('app:system-message-seeder')->daily()->runInBackground()->onOneServer();
Schedule::command('app:system-message-garbage-collector')->daily()->runInBackground()->onOneServer();
Schedule::command('passport:purge --revoked')->daily()->runInBackground()->onOneServer();
Schedule::command('version:check --force')->twiceDaily(9, 17)->withoutOverlapping()->runInBackground()->onOneServer();
Schedule::command('app:instance-stats-collector-command')->everySixHours(20)->withoutOverlapping()->runInBackground()->onOneServer();
Schedule::command('logs:clean-admin-security --days=14')->dailyAt('03:00')->runInBackground()->onOneServer();
Schedule::command('curated:expire-stale')->hourly()->runInBackground()->onOneServer();
Schedule::command('admin:check-activity')->everySixHours(15)->runInBackground()->onOneServer();
Schedule::command('app:purge-old-activities')->daily()->at('16:20')->runInBackground()->onOneServer();
Schedule::command('loops:publish-scheduled')->everyTenMinutes()->withoutOverlapping(5)->runInBackground()->onOneServer();

if (config('live.enabled')) {
    Schedule::command('live:viewer-counts')->everyMinute()->withoutOverlapping();
    Schedule::command('live:reconcile')->everyMinute()->withoutOverlapping();
}

if (config('denylist.enabled')) {
    Schedule::command('instances:sync-denylist')->dailyAt('04:15')->withoutOverlapping()->onOneServer()->runInBackground();
}

if (config('loops.admin_dashboard.autoUpdate')) {
    Schedule::command('admin:refresh-dashboard-30d')->everyThirtyMinutes()->runInBackground()->onOneServer();
}

if (config('loops.backups.enabled')) {
    Schedule::command('backup:clean')->daily()->at('01:00')->runInBackground()->onOneServer();
    Schedule::command('backup:run --only-db')->everySixHours()->runInBackground()->onOneServer();
}
