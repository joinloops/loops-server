<?php

namespace App\Console\Commands;

use App\Services\CollabService;
use Illuminate\Console\Command;

class ExpireCollabRequests extends Command
{
    protected $signature = 'collabs:expire';

    protected $description = 'Expire unanswered collaboration invites';

    public function handle(CollabService $collabs): int
    {
        $total = 0;

        do {
            $expired = $collabs->expireStale(500);
            $total += $expired;
        } while ($expired === 500);

        $this->info("Expired {$total} collaboration invite(s).");

        return self::SUCCESS;
    }
}
