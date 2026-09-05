<?php

namespace App\Services\Live;

use App\Models\LiveStream;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;

class LiveViewerService
{
    protected const PREFIX = 'loops:live:';

    protected const WINDOW = 30;

    public function heartbeat(LiveStream $stream, ?int $profileId, ?string $guestToken = null): string
    {
        $token = $profileId ? 'u:'.$profileId : 'g:'.($guestToken ?: (string) Str::uuid());
        $key = self::PREFIX.$stream->id.':viewers';

        Redis::zadd($key, now()->timestamp, $token);
        Redis::expire($key, 3600);

        $unique = self::PREFIX.$stream->id.':uniq';
        Redis::pfadd($unique, [$token]);
        Redis::expire($unique, 86400);

        return $token;
    }

    public function leave(LiveStream $stream, string $token): void
    {
        Redis::zrem(self::PREFIX.$stream->id.':viewers', $token);
    }

    public function count(LiveStream $stream): int
    {
        $key = self::PREFIX.$stream->id.':viewers';
        $cutoff = now()->timestamp - self::WINDOW;

        Redis::zremrangebyscore($key, '-inf', $cutoff);

        $count = (int) Redis::zcard($key);

        $peakKey = self::PREFIX.$stream->id.':peak';

        if ($count > (int) Redis::get($peakKey)) {
            Redis::set($peakKey, $count);
            Redis::expire($peakKey, 86400);
        }

        return $count;
    }

    public function peak(LiveStream $stream): int
    {
        return (int) Redis::get(self::PREFIX.$stream->id.':peak');
    }

    public function uniqueTotal(LiveStream $stream): int
    {
        return (int) Redis::pfcount(self::PREFIX.$stream->id.':uniq');
    }
}
