<?php

namespace App\Console\Commands;

use App\Jobs\User\UserAccountDeleteJob;
use App\Models\User;
use App\Services\AccountDeletionService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

#[Signature('loops:purge-deleted-accounts {--limit=100} {--dry-run : List due accounts without queueing purges}')]
#[Description('Queue purges for self-deleted accounts whose grace period has ended')]
class PurgeDeletedAccounts extends Command
{
    public function handle(AccountDeletionService $service): int
    {
        $limit = max(1, (int) $this->option('limit'));
        $dryRun = (bool) $this->option('dry-run');
        $queued = 0;

        $service->due()
            ->orderBy('delete_after')
            ->limit($limit)
            ->get(['id', 'username', 'delete_after'])
            ->each(function (User $user) use ($dryRun, &$queued) {
                if ($dryRun) {
                    $this->line("{$user->id}\t{$user->username}\t{$user->delete_after}");
                    $queued++;

                    return;
                }

                if (! Cache::add('loops:account-purge:queued:'.$user->id, 1, now()->addHours(4))) {
                    return;
                }

                UserAccountDeleteJob::dispatch($user->id, true, ['source' => 'scheduler'])->onQueue('default');
                $queued++;
            });

        $admins = User::where('status', 8)
            ->where('is_admin', true)
            ->whereNotNull('delete_after')
            ->where('delete_after', '<=', now())
            ->count();

        if ($admins) {
            $this->warn("{$admins} admin account(s) are pending deletion and must be removed with the CLI");
        }

        $this->info($dryRun ? "{$queued} account(s) due for purge" : "Queued {$queued} account purge(s)");

        return self::SUCCESS;
    }
}
