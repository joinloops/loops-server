<?php

namespace App\Models;

use App\Concerns\HasSnowflakePrimary;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property int $conversation_id
 * @property int $profile_id
 * @property string $type
 * @property string|null $body
 * @property array<array-key, mixed>|null $entities
 * @property int|null $video_id
 * @property string|null $ap_object_uri
 * @property int|null $in_reply_to_id
 * @property \Illuminate\Support\Carbon|null $edited_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Conversation|null $conversation
 * @property-read Message|null $inReplyTo
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\DmMedia> $media
 * @property-read int|null $media_count
 * @property-read \App\Models\Profile|null $sender
 * @property-read \App\Models\Video|null $video
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Message newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Message newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Message onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Message query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Message whereApObjectUri($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Message whereBody($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Message whereConversationId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Message whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Message whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Message whereEditedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Message whereEntities($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Message whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Message whereInReplyToId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Message whereProfileId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Message whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Message whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Message whereVideoId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Message withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Message withoutTrashed()
 *
 * @mixin \Eloquent
 */
class Message extends Model
{
    use HasSnowflakePrimary, SoftDeletes;

    /**
     * Indicates if the IDs are auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = false;

    public const TYPE_TEXT = 'text';

    public const TYPE_LOOP_SHARE = 'loop_share';

    public const TYPE_MEDIA = 'media';

    protected $guarded = [];

    protected $casts = [
        'entities' => 'array',
        'edited_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<Conversation, $this>
     */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    /**
     * @return BelongsTo<Profile, $this>
     */
    public function sender(): BelongsTo
    {
        return $this->belongsTo(Profile::class, 'profile_id');
    }

    /**
     * @return BelongsTo<Video, $this>
     */
    public function video(): BelongsTo
    {
        return $this->belongsTo(Video::class);
    }

    /**
     * @return HasMany<DmMedia, $this>
     */
    public function media(): HasMany
    {
        return $this->hasMany(DmMedia::class, 'message_id')->orderBy('order');
    }

    /**
     * @return BelongsTo<Message, $this>
     */
    public function inReplyTo(): BelongsTo
    {
        return $this->belongsTo(self::class, 'in_reply_to_id');
    }
}
