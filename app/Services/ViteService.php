<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Vite;

class ViteService
{
    public static function get($clear = false)
    {
        if ($clear) {
            Cache::forget('vite:version');
        }

        return Cache::rememberForever('vite:version', function () {
            return Vite::manifestHash();
        });
    }
}
