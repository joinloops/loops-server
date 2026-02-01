<?php

namespace App\Console\Commands;

use App\Services\BootstrapService;
use Illuminate\Console\Command;

class EnsureBoottimeEnvCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:ensure-boottime';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Ensure boot-time environment checks pass (directories, permissions, etc.)';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        try {
            BootstrapService::ensureBoottimeEnvironment();
            $this->info('All boot-time environment checks passed.');

            return Command::SUCCESS;
        } catch (\RuntimeException $e) {
            $this->error($e->getMessage());

            return Command::FAILURE;
        }
    }
}
