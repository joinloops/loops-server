<?php

use App\Console\Commands\SnapshotUsage;
use Illuminate\Support\Facades\Schedule;

Schedule::command(SnapshotUsage::class)->dailyAt('00:05')->timezone('UTC')->onOneServer();
Schedule::command('app:expire-user-register-verifications')->everyFiveMinutes()->onOneServer();
Schedule::command('horizon:snapshot')->hourly()->onOneServer();
Schedule::command('instances:update-stats --create-missing')->daily()->at('04:20')->onOneServer();

if (config('loops.admin_dashboard.autoUpdate')) {
    Schedule::command('admin:refresh-dashboard-30d')->everyThirtyMinutes()->onOneServer();
}

if (config('loops.backups.enabled')) {
    Schedule::command('backup:clean')->daily()->at('01:00')->onOneServer();
    Schedule::command('backup:run --only-db')->everySixHours()->onOneServer();
}
