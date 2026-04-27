<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class BlockedTerm extends Model
{
    protected $fillable = ['term', 'type', 'note', 'created_by'];

    public const CACHE_KEY = 'moderation:blocked_terms:v1';

    public const COUNTS_CACHE_KEY = 'admin:blocked-terms:counts';

    public const TYPE_BLOCK = 'block';

    public const TYPE_ALLOW = 'allow';

    protected static function booted(): void
    {
        $flush = fn () => Cache::forget(self::CACHE_KEY);

        static::saved($flush);
        static::deleted($flush);
    }

    public static function lists(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, function () {
            $rows = self::query()->get(['term', 'type']);

            return [
                self::TYPE_BLOCK => $rows
                    ->where('type', self::TYPE_BLOCK)
                    ->pluck('term')
                    ->map(fn ($t) => mb_strtolower(trim($t)))
                    ->filter()
                    ->values()
                    ->all(),
                self::TYPE_ALLOW => $rows
                    ->where('type', self::TYPE_ALLOW)
                    ->pluck('term')
                    ->map(fn ($t) => mb_strtolower(trim($t)))
                    ->filter()
                    ->values()
                    ->all(),
            ];
        });
    }

    public static function flushCache(): void
    {
        Cache::forget(self::CACHE_KEY);
        Cache::forget(self::COUNTS_CACHE_KEY);
    }
}
