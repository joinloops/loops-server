<?php

namespace App\Console\Commands;

use App\Jobs\UpdateUserInterestsJob;
use App\Models\User;
use App\Services\FrontendService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class UpdateFeedInterestsCommand extends Command
{
    protected $signature = 'feed:update-interests 
                          {--profile= : Specific profile ID to update}
                          {--active-days=7 : Only update profiles active in last N days}
                          {--cache-ttl=48 : Hours to cache completed updates (default 48)}
                          {--force : Force update even if recently processed}';

    protected $description = 'Update user interests for personalized feed recommendations';

    public function handle(): int
    {
        $force = $this->option('force');
        $cacheTtl = (int) $this->option('cache-ttl') ?: 48;

        $config = FrontendService::getCache();
        $enabled = data_get($config, 'fyf', false);

        if (! $enabled) {
            return 0;
        }

        if ($profileId = $this->option('profile')) {
            return $this->updateSingleProfile((int) $profileId, $force, $cacheTtl);
        }

        return $this->updateBulkProfiles($force, $cacheTtl);
    }

    protected function updateSingleProfile(int $profileId, bool $force, int $cacheTtl): int
    {
        $cacheKey = "user_interests_updated:{$profileId}";

        if (! $force && Cache::has($cacheKey)) {
            $this->warn("Profile {$profileId} was recently updated. Use --force to override.");

            return 0;
        }

        $this->info("Updating interests for profile {$profileId}...");
        UpdateUserInterestsJob::dispatch($profileId);
        Cache::put($cacheKey, now()->timestamp, now()->addHours($cacheTtl));

        return 0;
    }

    protected function updateBulkProfiles(bool $force, int $cacheTtl): int
    {
        $activeDays = (int) $this->option('active-days') ?: 7;

        $profiles = User::where('status', 1)
            ->where('last_active_at', '>=', now()->subDays($activeDays))
            ->pluck('profile_id');

        if ($profiles->isEmpty()) {
            $this->info('No active profiles found.');

            return 0;
        }

        $this->info("Found {$profiles->count()} active profiles (last {$activeDays} days)...");

        $queued = 0;
        $skipped = 0;

        $bar = $this->output->createProgressBar($profiles->count());
        $bar->start();

        foreach ($profiles as $profileId) {
            $cacheKey = "user_interests_updated:{$profileId}";

            if (! $force && Cache::has($cacheKey)) {
                $skipped++;
            } else {
                UpdateUserInterestsJob::dispatch((int) $profileId)
                    ->delay(now()->addSeconds(random_int(0, 300)));
                Cache::put($cacheKey, now()->timestamp, now()->addHours($cacheTtl));
                $queued++;
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $this->info("✓ Queued: {$queued} profiles");
        if ($skipped > 0) {
            $this->info("⊘ Skipped: {$skipped} profiles (recently updated)");
            $this->comment('  Use --force to process all profiles regardless of cache');
        }
        $this->info("  Cache TTL: {$cacheTtl} hours");

        return 0;
    }
}
