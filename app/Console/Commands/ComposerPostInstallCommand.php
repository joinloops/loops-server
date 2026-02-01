<?php

namespace App\Console\Commands;

use App\Services\BootstrapService;
use App\Services\SettingsFileService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class ComposerPostInstallCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:composer-post-install-command';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clear specific caches after installing';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        try {
            DB::connection()->getPdo();

            Cache::forget('version_check_result');
            app(SettingsFileService::class)->flush();
            BootstrapService::ensureBoottimeEnvironment();

            $this->info('Post-install tasks completed successfully.');
        } catch (\Exception $e) {
            return 0;
        }
    }
}
