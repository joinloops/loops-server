<?php

namespace App\Models;

use App\Concerns\HasSnowflakePrimary;
use App\Concerns\HasSyncHashtagsFromCaption;
use App\Concerns\HasSyncMentionsFromCaption;
use App\Observers\CommentObserver;
use App\Services\HashidService;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[ObservedBy([CommentObserver::class])]
/**
 * @property int $id
 * @property int $video_id
 * @property int $profile_id
 * @property string|null $caption
 * @property array<array-key, mixed>|null $entities
 * @property int $likes
 * @property int $shares
 * @property int $replies
 * @property bool $can_reply
 * @property bool $is_pinned
 * @property bool $is_edited
 * @property bool $is_hidden
 * @property bool $is_sensitive
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property string $status
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property string|null $ap_id
 * @property string|null $remote_url
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\CommentReply> $children
 * @property-read int|null $children_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Mention> $mentions
 * @property-read \Illuminate\Database\Eloquent\Relations\BelongsToMany<Hashtag, $this, CommentHashtag, 'pivot'> $hashtags
 * @property-read int|null $mentions_count
 * @property-read \App\Models\Profile|null $profile
 * @property-read \App\Models\Video|null $video
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Comment newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Comment newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Comment onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Comment query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Comment whereApId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Comment whereCanReply($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Comment whereCaption($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Comment whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Comment whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Comment whereEntities($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Comment whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Comment whereIsEdited($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Comment whereIsHidden($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Comment whereIsPinned($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Comment whereIsSensitive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Comment whereLikes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Comment whereProfileId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Comment whereRemoteUrl($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Comment whereReplies($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Comment whereShares($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Comment whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Comment whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Comment whereVideoId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Comment withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Comment withoutTrashed()
 *
 * @property int $visibility
 * @property-read \App\Models\CommentHashtag|null $pivot
 * @property-read int|null $hashtags_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\QuoteAuthorization> $quoteAuthorizations
 * @property-read int|null $quote_authorizations_count
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Comment whereVisibility($value)
 *
 * @mixin \Eloquent
 */
class Comment extends Model
{
    use HasFactory, HasSnowflakePrimary, HasSyncHashtagsFromCaption, HasSyncMentionsFromCaption, SoftDeletes;

    protected $fillable = ['video_id', 'profile_id', 'caption', 'status', 'replies', 'is_sensitive', 'updated_at', 'is_edited', 'ap_id', 'remote_url', 'is_hidden'];

    /**
     * Indicates if the IDs are auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = false;

    protected function casts(): array
    {
        return [
            'entities' => 'array',
            'likes' => 'integer',
            'replies' => 'integer',
            'can_reply' => 'boolean',
            'is_pinned' => 'boolean',
            'is_edited' => 'boolean',
            'is_hidden' => 'boolean',
            'is_sensitive' => 'boolean',
        ];
    }

    /** @return HasMany<CommentReply, $this> */
    public function children(): HasMany
    {
        return $this->hasMany(CommentReply::class, 'comment_id', 'id');
    }

    /** @return BelongsTo<Profile, $this> */
    public function profile(): BelongsTo
    {
        return $this->belongsTo(Profile::class);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany<Hashtag, $this, CommentHashtag, 'pivot'>
     */
    public function hashtags()
    {
        return $this->belongsToMany(Hashtag::class, 'comment_hashtags')->using(CommentHashtag::class);
    }

    /** @return MorphMany<Mention, $this> */
    public function mentions(): MorphMany
    {
        return $this->morphMany(Mention::class, 'mentionable');
    }

    public function getObjectUrl($suffix = null): string
    {
        return $this->ap_id ?: url('/ap/users/'.$this->profile_id.'/comment/'.$this->id.$suffix);
    }

    public function permalink($suffix = null): string
    {
        return $this->ap_id ?: url('/ap/users/'.$this->profile_id.'/comment/'.$this->id.$suffix);
    }

    public function quoteAuthorizations(): MorphMany
    {
        return $this->morphMany(QuoteAuthorization::class, 'quotable');
    }

    public function shareUrl(): string
    {
        $vid = HashidService::encode((string) $this->video_id);
        $cid = HashidService::encode((string) $this->id);

        return url("/v/{$vid}?cid={$cid}");
    }

    /** @return BelongsTo<Video, $this> */
    public function video()
    {
        return $this->belongsTo(Video::class);
    }

    public function recalculateReplies()
    {
        $this->update(['replies' => CommentReply::where('comment_id', $this->id)->count()]);
    }
}
