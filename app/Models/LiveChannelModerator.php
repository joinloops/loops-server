<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $live_channel_id
 * @property int $profile_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LiveChannelModerator newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LiveChannelModerator newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LiveChannelModerator query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LiveChannelModerator whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LiveChannelModerator whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LiveChannelModerator whereLiveChannelId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LiveChannelModerator whereProfileId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LiveChannelModerator whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class LiveChannelModerator extends Model
{
    protected $fillable = [
        'live_channel_id',
        'profile_id',
    ];
}
