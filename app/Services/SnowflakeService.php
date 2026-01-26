<?php

namespace App\Services;

use Cache;
use Exception;
use Illuminate\Support\Carbon;

class SnowflakeService
{
    // The snowflake epoch is 2024-03-28T11:21:53+00:00
    const EPOCH_DATE = 1711624913000;

    /**
     * Generate the next 64-bit unique ID
     *
     * @throws Exception
     */
    public static function next(): int
    {
        $seq = Cache::get('snowflake:seq');

        if (! $seq) {
            Cache::put('snowflake:seq', 1);
            $seq = 1;
        } else {
            $seq = Cache::increment('snowflake:seq');
        }

        if ($seq > 4095) {
            Cache::put('snowflake:seq', 0);
            $seq = 0;
        }

        return ((round(microtime(true) * 1000) - 1711624913000) << 22)
        | (random_int(1, 31) << 17)
        | (random_int(1, 31) << 12)
        | $seq;
    }

    public static function byDate(?Carbon $ts = null)
    {
        if ($ts instanceof Carbon) {
            $ts = now()->parse($ts)->timestamp;
        } else {
            return self::next();
        }

        return ((round($ts * 1000) - 1711624913000) << 22)
        | (random_int(1, 31) << 17)
        | (random_int(1, 31) << 12)
        | 0;
    }
}
