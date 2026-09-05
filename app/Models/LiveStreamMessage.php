<?php

namespace App\Models;

use App\Concerns\HasSnowflakePrimary;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property int $live_stream_id
 * @property int $profile_id
 * @property int $seq
 * @property string $type
 * @property string|null $body
 * @property array<array-key, mixed>|null $entities
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read \App\Models\Profile|null $profile
 * @property-read \App\Models\LiveStream|null $stream
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LiveStreamMessage newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LiveStreamMessage newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LiveStreamMessage onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LiveStreamMessage query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LiveStreamMessage whereBody($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LiveStreamMessage whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LiveStreamMessage whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LiveStreamMessage whereEntities($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LiveStreamMessage whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LiveStreamMessage whereLiveStreamId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LiveStreamMessage whereProfileId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LiveStreamMessage whereSeq($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LiveStreamMessage whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LiveStreamMessage whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LiveStreamMessage withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LiveStreamMessage withoutTrashed()
 *
 * @mixin \Eloquent
 */
class LiveStreamMessage extends Model
{
    use HasSnowflakePrimary, SoftDeletes;

    /**
     * Indicates if the IDs are auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = false;

    public const TYPE_MESSAGE = 'message';

    public const TYPE_SYSTEM = 'system';

    protected $fillable = [
        'live_stream_id',
        'profile_id',
        'type',
        'body',
        'entities',
    ];

    protected $casts = [
        'entities' => 'array',
    ];

    public function stream()
    {
        return $this->belongsTo(LiveStream::class, 'live_stream_id');
    }

    public function profile()
    {
        return $this->belongsTo(Profile::class);
    }
}
