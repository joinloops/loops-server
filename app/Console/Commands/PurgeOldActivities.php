<?php

namespace App\Console\Commands;

use App\Models\Activity;
use Illuminate\Console\Command;

class PurgeOldActivities extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:purge-old-activities';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Purge federation activitylog entries older than 14 days';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $count = Activity::where('created_at', '<', now()->subDays(14))->delete();
        $this->info("Purged {$count} activities!");
    }
}
