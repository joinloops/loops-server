<?php

namespace App\Models;

use App\Concerns\HasSnowflakePrimary;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property int $profile_id
 * @property string $public_id
 * @property string $stream_key
 * @property string|null $title
 * @property string|null $description
 * @property int $visibility
 * @property bool $chat_enabled
 * @property string $chat_mode
 * @property bool $is_enabled
 * @property \Illuminate\Support\Carbon|null $key_rotated_at
 * @property int|null $current_stream_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\LiveChannelBan> $bans
 * @property-read int|null $bans_count
 * @property-read \App\Models\LiveStream|null $currentStream
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\LiveChannelModerator> $moderators
 * @property-read int|null $moderators_count
 * @property-read \App\Models\Profile|null $profile
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\LiveStream> $streams
 * @property-read int|null $streams_count
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LiveChannel newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LiveChannel newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LiveChannel query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LiveChannel whereChatEnabled($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LiveChannel whereChatMode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LiveChannel whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LiveChannel whereCurrentStreamId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LiveChannel whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LiveChannel whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LiveChannel whereIsEnabled($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LiveChannel whereKeyRotatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LiveChannel whereProfileId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LiveChannel wherePublicId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LiveChannel whereStreamKey($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LiveChannel whereTitle($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LiveChannel whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LiveChannel whereVisibility($value)
 *
 * @mixin \Eloquent
 */
class LiveChannel extends Model
{
    use HasSnowflakePrimary;

    /**
     * Indicates if the IDs are auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = false;

    protected $fillable = [
        'profile_id',
        'public_id',
        'stream_key',
        'title',
        'description',
        'visibility',
        'chat_enabled',
        'chat_mode',
        'is_enabled',
        'key_rotated_at',
        'current_stream_id',
    ];

    protected $casts = [
        'chat_enabled' => 'boolean',
        'is_enabled' => 'boolean',
        'visibility' => 'integer',
        'key_rotated_at' => 'datetime',
    ];

    protected $hidden = ['stream_key'];

    public function profile()
    {
        return $this->belongsTo(Profile::class);
    }

    public function streams()
    {
        return $this->hasMany(LiveStream::class);
    }

    public function currentStream()
    {
        return $this->belongsTo(LiveStream::class, 'current_stream_id');
    }

    public function bans()
    {
        return $this->hasMany(LiveChannelBan::class);
    }

    public function moderators()
    {
        return $this->hasMany(LiveChannelModerator::class);
    }

    public static function newStreamKey(): string
    {
        return Str::random(48);
    }

    public function ingestPath(): string
    {
        $app = trim((string) config('live.rtmp.app'), '/');

        return $app === '' ? $this->public_id : $app.'/'.$this->public_id;
    }
}
