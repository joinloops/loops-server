<?php

namespace App\Listeners;

use App\Events\SettingsUpdated;
use App\Services\ConfigService;
use App\Services\SettingsFileService;
use Illuminate\Contracts\Queue\ShouldQueue;

class RegenerateSettingsFile implements ShouldQueue
{
    public function __construct() {}

    public function handle(SettingsUpdated $event)
    {
        (new SettingsFileService)->generatePublicConfig();
        (new SettingsFileService)->generateAdminConfig();
        app(ConfigService::class)->forYouFeed(true);
        app(ConfigService::class)->federation(true);
        app(ConfigService::class)->federationMode(true);
        app(ConfigService::class)->federationAllowedServers(true);
        app(ConfigService::class)->federationAuthorizedFetch(true);
    }
}
