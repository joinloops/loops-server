<?php

namespace App\Console\Commands;

use App\Models\UserRegisterVerify;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class ExpireUserRegisterVerificationsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:expire-user-register-verifications';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete expired or already-used user registration verification records.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $cutoff = Carbon::now()->subHours(4);

        $deleted = UserRegisterVerify::where(function ($query) use ($cutoff) {
            $query->where('created_at', '<', $cutoff)
                ->orWhereNotNull('verified_at');
        })
            ->delete();

        $this->info("Expired {$deleted} user registration verification record(s).");

        return self::SUCCESS;
    }
}
