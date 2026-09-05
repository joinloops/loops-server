<?php

namespace App\Providers;

use App\Contracts\LiveProvider;
use App\Services\Live\Providers\MediaMtxProvider;
use Illuminate\Support\ServiceProvider;

class LiveServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(LiveProvider::class, function () {
            return match (config('live.provider')) {
                default => new MediaMtxProvider,
            };
        });
    }
}
