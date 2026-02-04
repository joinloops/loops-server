<?php

namespace App\Models;

use App\Observers\FollowerObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[ObservedBy([FollowerObserver::class])]
/**
 * @property int $id
 * @property int $profile_id
 * @property int $following_id
 * @property bool $following_is_local
 * @property bool $profile_is_local
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Profile|null $following
 * @property-read \App\Models\Profile|null $profile
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Follower newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Follower newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Follower query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Follower whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Follower whereFollowingId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Follower whereFollowingIsLocal($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Follower whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Follower whereProfileId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Follower whereProfileIsLocal($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Follower whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class Follower extends Model
{
    use HasFactory;

    protected $fillable = ['profile_id', 'following_id', 'profile_is_local', 'following_is_local'];

    protected $visible = ['profile_id', 'following_id'];

    protected $casts = [
        'profile_is_local' => 'boolean',
        'following_is_local' => 'boolean',
    ];

    public function profile()
    {
        return $this->belongsTo(Profile::class, 'profile_id');
    }

    public function following()
    {
        return $this->belongsTo(Profile::class, 'following_id');
    }
}
