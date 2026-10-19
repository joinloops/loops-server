<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property-read \App\Models\Profile|null $profile
 *
 * @method static Builder<static>|UsernameHistory newModelQuery()
 * @method static Builder<static>|UsernameHistory newQuery()
 * @method static Builder<static>|UsernameHistory open()
 * @method static Builder<static>|UsernameHistory query()
 *
 * @mixin \Eloquent
 */
class UsernameHistory extends Model
{
    public const SOURCE_INITIAL = 'initial';

    public const SOURCE_LOCAL_CHANGE = 'local_change';

    public const SOURCE_REMOTE_UPDATE = 'remote_update';

    public const SOURCE_ADMIN = 'admin';

    protected $table = 'username_history';

    protected $fillable = [
        'profile_id',
        'username',
        'domain',
        'local',
        'source',
        'is_revert',
        'started_at',
        'ended_at',
    ];

    protected $casts = [
        'local' => 'boolean',
        'is_revert' => 'boolean',
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
    ];

    public function profile(): BelongsTo
    {
        return $this->belongsTo(Profile::class);
    }

    protected function scopeOpen(Builder $query): Builder
    {
        return $query->whereNull('ended_at');
    }

    public static function recordInitial(Profile $profile): self
    {
        return static::create([
            'profile_id' => $profile->id,
            'username' => $profile->username,
            'domain' => $profile->local ? null : $profile->domain,
            'local' => (bool) $profile->local,
            'source' => self::SOURCE_INITIAL,
            'is_revert' => false,
            'started_at' => now(),
        ]);
    }
}
