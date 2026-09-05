<?php

namespace App\Models;

use App\Concerns\HasSnowflakePrimary;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $live_channel_id
 * @property int $profile_id
 * @property string $status
 * @property string|null $title
 * @property string|null $thumbnail_url
 * @property int $visibility
 * @property string|null $source_id
 * @property int $peak_viewers
 * @property int $total_viewers
 * @property int $message_count
 * @property \Illuminate\Support\Carbon|null $started_at
 * @property \Illuminate\Support\Carbon|null $ended_at
 * @property \Illuminate\Support\Carbon|null $last_seen_at
 * @property int|null $replay_video_id
 * @property string|null $ap_id
 * @property string|null $context_uri
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\LiveChannel|null $channel
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\LiveStreamMessage> $messages
 * @property-read int|null $messages_count
 * @property-read \App\Models\Profile|null $profile
 *
 * @method static Builder<static>|LiveStream active()
 * @method static Builder<static>|LiveStream live()
 * @method static Builder<static>|LiveStream newModelQuery()
 * @method static Builder<static>|LiveStream newQuery()
 * @method static Builder<static>|LiveStream query()
 * @method static Builder<static>|LiveStream whereApId($value)
 * @method static Builder<static>|LiveStream whereContextUri($value)
 * @method static Builder<static>|LiveStream whereCreatedAt($value)
 * @method static Builder<static>|LiveStream whereEndedAt($value)
 * @method static Builder<static>|LiveStream whereId($value)
 * @method static Builder<static>|LiveStream whereLastSeenAt($value)
 * @method static Builder<static>|LiveStream whereLiveChannelId($value)
 * @method static Builder<static>|LiveStream whereMessageCount($value)
 * @method static Builder<static>|LiveStream wherePeakViewers($value)
 * @method static Builder<static>|LiveStream whereProfileId($value)
 * @method static Builder<static>|LiveStream whereReplayVideoId($value)
 * @method static Builder<static>|LiveStream whereSourceId($value)
 * @method static Builder<static>|LiveStream whereStartedAt($value)
 * @method static Builder<static>|LiveStream whereStatus($value)
 * @method static Builder<static>|LiveStream whereThumbnailUrl($value)
 * @method static Builder<static>|LiveStream whereTitle($value)
 * @method static Builder<static>|LiveStream whereTotalViewers($value)
 * @method static Builder<static>|LiveStream whereUpdatedAt($value)
 * @method static Builder<static>|LiveStream whereVisibility($value)
 *
 * @mixin \Eloquent
 */
class LiveStream extends Model
{
    use HasSnowflakePrimary;

    /**
     * Indicates if the IDs are auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = false;

    public const STATUS_PREPARING = 'preparing';

    public const STATUS_LIVE = 'live';

    public const STATUS_ENDED = 'ended';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'live_channel_id',
        'profile_id',
        'status',
        'title',
        'thumbnail_url',
        'visibility',
        'source_id',
        'peak_viewers',
        'total_viewers',
        'message_count',
        'started_at',
        'ended_at',
        'last_seen_at',
        'replay_video_id',
        'ap_id',
        'context_uri',
    ];

    protected $casts = [
        'visibility' => 'integer',
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
        'last_seen_at' => 'datetime',
    ];

    /** @return BelongsTo<LiveChannel, $this> */
    public function channel()
    {
        return $this->belongsTo(LiveChannel::class, 'live_channel_id');
    }

    /** @return BelongsTo<Profile, $this> */
    public function profile()
    {
        return $this->belongsTo(Profile::class);
    }

    /** @return HasMany<LiveStreamMessage, $this> */
    public function messages()
    {
        return $this->hasMany(LiveStreamMessage::class);
    }

    protected function scopeLive(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_LIVE);
    }

    protected function scopeActive(Builder $query): Builder
    {
        return $query->whereIn('status', [self::STATUS_PREPARING, self::STATUS_LIVE]);
    }

    public function isLive(): bool
    {
        return $this->status === self::STATUS_LIVE;
    }

    public function isOver(): bool
    {
        return in_array($this->status, [self::STATUS_ENDED, self::STATUS_FAILED], true);
    }
}
