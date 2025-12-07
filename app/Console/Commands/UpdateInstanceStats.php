<?php

namespace App\Console\Commands;

use App\Jobs\Federation\DiscoverInstance;
use App\Models\Instance;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;

class UpdateInstanceStats extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'instances:update-stats 
                            {--domain= : Update stats for a specific domain only}
                            {--create-missing : Create missing instance records}
                            {--limit= : Limit number of missing instances to discover per run}
                            {--batch-size=50 : Number of discovery jobs to dispatch per batch}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update instance statistics efficiently and sync missing records';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $domain = $this->option('domain');
        $createMissing = $this->option('create-missing');

        $this->info('Starting instance statistics update...');

        if ($createMissing) {
            $this->createMissingInstances();
        }

        $this->updateUserCounts($domain);
        $this->updateVideoCounts($domain);
        $this->updateCommentCounts($domain);
        $this->updateReplyCounts($domain);
        $this->updateFollowerCounts($domain);
        $this->updateFollowingCounts($domain);
        $this->updateReportCounts($domain);

        $this->info('Instance statistics updated successfully!');

        return Command::SUCCESS;
    }

    /**
     * Create missing instance records from profiles table
     */
    protected function createMissingInstances()
    {
        $this->info('Discovering missing instances...');

        $limit = $this->option('limit');
        $batchSize = (int) $this->option('batch-size');

        $query = DB::table('profiles')
            ->select('domain')
            ->whereNotNull('domain')
            ->where('domain', '!=', '')
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('instances')
                    ->whereColumn('instances.domain', 'profiles.domain')
                    ->where(function ($q) {
                        $q->where('is_blocked', true)
                            ->orWhere('is_silenced', true);
                    });
            })
            ->distinct();

        if ($limit) {
            $query->limit((int) $limit);
        }

        $missingDomains = $query->pluck('domain');

        if ($missingDomains->isEmpty()) {
            $this->info('No missing instances found.');

            return;
        }

        $total = $missingDomains->count();
        $this->info("Found {$total} missing instances. Dispatching discovery jobs in batches of {$batchSize}...");

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $chunks = $missingDomains->chunk($batchSize);
        $dispatchedCount = 0;

        foreach ($chunks as $chunk) {
            $jobs = $chunk->map(function ($domain) {
                return new DiscoverInstance('https://'.$domain);
            })->toArray();

            Bus::batch($jobs)
                ->allowFailures()
                ->onQueue('activitypub-in')
                ->dispatch();

            $dispatchedCount += count($jobs);
            $bar->advance(count($jobs));
        }

        $bar->finish();
        $this->newLine();
        $this->info("Dispatched {$dispatchedCount} discovery jobs in ".$chunks->count().' batches.');
    }

    /**
     * Update user counts (profiles) for each instance
     */
    protected function updateUserCounts($domain = null)
    {
        $this->info('Updating user counts...');

        $query = "
            UPDATE instances i
            INNER JOIN (
                SELECT 
                    domain,
                    COUNT(*) as user_count
                FROM profiles
                WHERE domain IS NOT NULL
                AND domain != ''
                AND local = 0
                GROUP BY domain
            ) p ON i.domain = p.domain
            SET i.user_count = p.user_count
        ";

        if ($domain) {
            $query .= ' WHERE i.domain = ?';
            DB::update($query, [$domain]);
        } else {
            DB::update($query);
        }

        $this->line('✓ User counts updated');
    }

    /**
     * Update video counts for each instance
     */
    protected function updateVideoCounts($domain = null)
    {
        $this->info('Updating video counts...');

        $query = "
            UPDATE instances i
            INNER JOIN (
                SELECT 
                    p.domain,
                    COUNT(*) as video_count
                FROM videos v
                INNER JOIN profiles p ON v.profile_id = p.id
                WHERE p.domain IS NOT NULL
                AND p.domain != ''
                AND v.is_local = 0
                AND v.status = 1
                GROUP BY p.domain
            ) v ON i.domain = v.domain
            SET i.video_count = v.video_count
        ";

        if ($domain) {
            $query .= ' WHERE i.domain = ?';
            DB::update($query, [$domain]);
        } else {
            DB::update($query);
        }

        $this->line('✓ Video counts updated');
    }

    /**
     * Update comment counts for each instance
     */
    protected function updateCommentCounts($domain = null)
    {
        $this->info('Updating comment counts...');

        $query = "
            UPDATE instances i
            INNER JOIN (
                SELECT 
                    p.domain,
                    COUNT(*) as comment_count
                FROM comments c
                INNER JOIN profiles p ON c.profile_id = p.id
                WHERE p.domain IS NOT NULL
                AND p.domain != ''
                AND c.status = 'active'
                GROUP BY p.domain
            ) c ON i.domain = c.domain
            SET i.comment_count = c.comment_count
        ";

        if ($domain) {
            $query .= ' WHERE i.domain = ?';
            DB::update($query, [$domain]);
        } else {
            DB::update($query);
        }

        $this->line('✓ Comment counts updated');
    }

    /**
     * Update reply counts for each instance
     */
    protected function updateReplyCounts($domain = null)
    {
        $this->info('Updating reply counts...');

        $query = "
            UPDATE instances i
            INNER JOIN (
                SELECT 
                    p.domain,
                    COUNT(*) as reply_count
                FROM comment_replies cr
                INNER JOIN profiles p ON cr.profile_id = p.id
                WHERE p.domain IS NOT NULL
                AND p.domain != ''
                AND cr.status = 'active'
                GROUP BY p.domain
            ) cr ON i.domain = cr.domain
            SET i.reply_count = cr.reply_count
        ";

        if ($domain) {
            $query .= ' WHERE i.domain = ?';
            DB::update($query, [$domain]);
        } else {
            DB::update($query);
        }

        $this->line('✓ Reply counts updated');
    }

    /**
     * Update follower counts for each instance
     */
    protected function updateFollowerCounts($domain = null)
    {
        $this->info('Updating follower counts...');

        $query = "
            UPDATE instances i
            INNER JOIN (
                SELECT 
                    p.domain,
                    COUNT(*) as follower_count
                FROM followers f
                INNER JOIN profiles p ON f.profile_id = p.id
                WHERE p.domain IS NOT NULL
                AND p.domain != ''
                AND f.profile_is_local = 0
                GROUP BY p.domain
            ) f ON i.domain = f.domain
            SET i.follower_count = f.follower_count
        ";

        if ($domain) {
            $query .= ' WHERE i.domain = ?';
            DB::update($query, [$domain]);
        } else {
            DB::update($query);
        }

        $this->line('✓ Follower counts updated');
    }

    /**
     * Update following counts for each instance
     */
    protected function updateFollowingCounts($domain = null)
    {
        $this->info('Updating following counts...');

        $query = "
            UPDATE instances i
            INNER JOIN (
                SELECT 
                    p.domain,
                    COUNT(*) as following_count
                FROM followers f
                INNER JOIN profiles p ON f.following_id = p.id
                WHERE p.domain IS NOT NULL
                AND p.domain != ''
                AND f.profile_is_local = 1
                GROUP BY p.domain
            ) f ON i.domain = f.domain
            SET i.following_count = f.following_count
        ";

        if ($domain) {
            $query .= ' WHERE i.domain = ?';
            DB::update($query, [$domain]);
        } else {
            DB::update($query);
        }

        $this->line('✓ Following counts updated');
    }

    /**
     * Update report counts for each instance
     */
    protected function updateReportCounts($domain = null)
    {
        $this->info('Updating report counts...');

        $query = "
            UPDATE instances i
            INNER JOIN (
                SELECT 
                    domain,
                    COUNT(*) as report_count
                FROM reports
                WHERE domain IS NOT NULL
                AND domain != ''
                AND is_remote = 1
                GROUP BY domain
            ) r ON i.domain = r.domain
            SET i.report_count = r.report_count
        ";

        if ($domain) {
            $query .= ' WHERE i.domain = ?';
            DB::update($query, [$domain]);
        } else {
            DB::update($query);
        }

        $this->line('✓ Report counts updated');
    }
}
