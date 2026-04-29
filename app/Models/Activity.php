<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $profile_id
 * @property string $type
 * @property string|null $object_type
 * @property int|null $object_id
 * @property string $activity_id
 * @property string|null $to
 * @property string|null $cc
 * @property string|null $bcc
 * @property string $raw_activity
 * @property array<array-key, mixed> $payload
 * @property bool $processed
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property-read \App\Models\Profile $profile
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Activity newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Activity newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Activity ofType(string $type)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Activity processed(bool $processed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Activity query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Activity since($date)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Activity whereActivityId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Activity whereBcc($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Activity whereCc($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Activity whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Activity whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Activity whereObjectId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Activity whereObjectType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Activity wherePayload($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Activity whereProcessed($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Activity whereProfileId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Activity whereRawActivity($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Activity whereTo($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Activity whereType($value)
 *
 * @mixin \Eloquent
 */
class Activity extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'profile_id',
        'type',
        'object_type',
        'object_id',
        'activity_id',
        'to',
        'cc',
        'raw_activity',
        'bcc',
        'payload',
        'processed',
        'created_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'raw_activity' => 'array',
        'processed' => 'boolean',
        'created_at' => 'datetime',
    ];

    public function profile()
    {
        return $this->belongsTo(Profile::class);
    }

    public function scopeProcessed($query, bool $processed = true)
    {
        return $query->where('processed', $processed);
    }

    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }

    public function scopeSince($query, $date)
    {
        return $query->where('created_at', '>=', $date);
    }

    public function markAsProcessed()
    {
        $this->update(['processed' => 1]);
    }
}
