<?php

namespace App\Models;

use App\Concerns\HasSnowflakePrimary;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $live_channel_id
 * @property int $profile_id
 * @property int $moderator_id
 * @property \Illuminate\Support\Carbon|null $expires_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LiveChannelBan newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LiveChannelBan newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LiveChannelBan query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LiveChannelBan whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LiveChannelBan whereExpiresAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LiveChannelBan whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LiveChannelBan whereLiveChannelId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LiveChannelBan whereModeratorId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LiveChannelBan whereProfileId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LiveChannelBan whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class LiveChannelBan extends Model
{
    use HasSnowflakePrimary;

    /**
     * Indicates if the IDs are auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = false;

    protected $fillable = [
        'live_channel_id',
        'profile_id',
        'moderator_id',
        'expires_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
    ];

    public function isActive(): bool
    {
        return $this->expires_at === null || $this->expires_at->isFuture();
    }
}
