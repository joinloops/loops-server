<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Cache;

class AccessRule extends Model
{
    public const TYPE_IP = 'ip';

    public const TYPE_EMAIL = 'email';

    public const TYPE_USERNAME = 'username';

    public const TYPES = [self::TYPE_IP, self::TYPE_EMAIL, self::TYPE_USERNAME];

    public const MATCH_EXACT = 'exact';

    public const MATCH_CIDR = 'cidr';

    public const MATCH_RANGE = 'range';

    public const MATCH_DOMAIN = 'domain';

    public const MATCH_PATTERN = 'pattern';

    public const CACHE_KEY = 'loops:access-rules:lists:v1';

    public const COUNTS_CACHE_KEY = 'loops:access-rules:counts:v1';

    protected $fillable = [
        'type',
        'match_type',
        'value',
        'ip_start',
        'ip_end',
        'blocks_login',
        'blocks_registration',
        'note',
        'expires_at',
        'created_by',
        'source_profile_id',
    ];

    protected $casts = [
        'blocks_login' => 'boolean',
        'blocks_registration' => 'boolean',
        'expires_at' => 'datetime',
        'last_hit_at' => 'datetime',
        'hits' => 'integer',
    ];

    protected static function booted(): void
    {
        $flush = fn () => self::flushCache();

        static::saved($flush);
        static::deleted($flush);
    }

    public static function flushCache(): void
    {
        Cache::forget(self::CACHE_KEY);
        Cache::forget(self::COUNTS_CACHE_KEY);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    protected function scopeActive(Builder $query): Builder
    {
        return $query->where(function ($q) {
            $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
        });
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }
}
