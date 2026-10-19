<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property-read \App\Models\Profile|null $profile
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UsernameReservation newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UsernameReservation newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UsernameReservation query()
 *
 * @mixin \Eloquent
 */
class UsernameReservation extends Model
{
    protected $fillable = [
        'profile_id',
        'username',
        'reserved_at',
    ];

    protected $casts = [
        'reserved_at' => 'datetime',
    ];

    public function profile(): BelongsTo
    {
        return $this->belongsTo(Profile::class);
    }
}
