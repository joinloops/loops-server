<?php

namespace App\Models;

use App\Concerns\HasSnowflakePrimary;
use App\Concerns\HasSyncHashtagsFromCaption;
use App\Concerns\HasSyncMentionsFromCaption;
use App\Observers\VideoObserver;
use App\Services\HashidService;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

#[ObservedBy([VideoObserver::class])]
/**
 * @property int $id
 * @property string|null $profile_id
 * @property string|null $vid
 * @property string|null $vid_optimized
 * @property int $status
 * @property int $visibility
 * @property int|null $duration
 * @property int|null $size_kb
 * @property string|null $caption
 * @property bool $is_local
 * @property string|null $uri
 * @property string|null $remote_media_url
 * @property string|null $remote_thumb_url
 * @property string|null $remote_hls_url
 * @property string|null $tags
 * @property int $likes
 * @property int $comments
 * @property int $shares
 * @property int $views
 * @property bool $is_sensitive
 * @property int $is_adult
 * @property int $has_audio
 * @property int $has_thumb
 * @property int $has_processed
 * @property int $is_approved
 * @property string|null $features
 * @property array<array-key, mixed>|null $media_metadata
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property int $comment_state
 * @property string|null $cw_title
 * @property string|null $cw_body
 * @property string|null $sha512_hash
 * @property int $has_hls
 * @property int $can_download
 * @property int $can_duet
 * @property bool $is_duet
 * @property string|null $original_duet_id
 * @property string|null $duet_path
 * @property int $duet_layout
 * @property int $can_stitch
 * @property int $is_pinned
 * @property int|null $pinned_order
 * @property string|null $category
 * @property string|null $ap_published_at
 * @property string|null $last_fetched_at
 * @property int $fetch_failure_count
 * @property int $is_edited
 * @property string|null $alt_text
 * @property string|null $lang
 * @property bool $contains_ai
 * @property bool $contains_ad
 * @property-read \Illuminate\Database\Eloquent\Relations\BelongsToMany<Hashtag, $this, VideoHashtag, 'pivot'> $hashtags
 * @property-read int|null $hashtags_count
 * @property-read \App\Models\Profile|null $profile
 *
 * @method static Builder<static>|Video canComment()
 * @method static Builder<static>|Video newModelQuery()
 * @method static Builder<static>|Video newQuery()
 * @method static Builder<static>|Video published()
 * @method static Builder<static>|Video query()
 * @method static Builder<static>|Video whereApPublishedAt($value)
 * @method static Builder<static>|Video whereCanDownload($value)
 * @method static Builder<static>|Video whereCanDuet($value)
 * @method static Builder<static>|Video whereCanStitch($value)
 * @method static Builder<static>|Video whereCaption($value)
 * @method static Builder<static>|Video whereCategory($value)
 * @method static Builder<static>|Video whereCommentState($value)
 * @method static Builder<static>|Video whereComments($value)
 * @method static Builder<static>|Video whereCreatedAt($value)
 * @method static Builder<static>|Video whereCwBody($value)
 * @method static Builder<static>|Video whereCwTitle($value)
 * @method static Builder<static>|Video whereDuration($value)
 * @method static Builder<static>|Video whereFeatures($value)
 * @method static Builder<static>|Video whereFetchFailureCount($value)
 * @method static Builder<static>|Video whereHasAudio($value)
 * @method static Builder<static>|Video whereHasHls($value)
 * @method static Builder<static>|Video whereHasProcessed($value)
 * @method static Builder<static>|Video whereHasThumb($value)
 * @method static Builder<static>|Video whereId($value)
 * @method static Builder<static>|Video whereIsAdult($value)
 * @method static Builder<static>|Video whereIsApproved($value)
 * @method static Builder<static>|Video whereIsLocal($value)
 * @method static Builder<static>|Video whereIsPinned($value)
 * @method static Builder<static>|Video whereIsSensitive($value)
 * @method static Builder<static>|Video whereLastFetchedAt($value)
 * @method static Builder<static>|Video whereLikes($value)
 * @method static Builder<static>|Video whereMediaMetadata($value)
 * @method static Builder<static>|Video wherePinnedOrder($value)
 * @method static Builder<static>|Video whereProfileId($value)
 * @method static Builder<static>|Video whereRemoteHlsUrl($value)
 * @method static Builder<static>|Video whereRemoteMediaUrl($value)
 * @method static Builder<static>|Video whereRemoteThumbUrl($value)
 * @method static Builder<static>|Video whereSha512Hash($value)
 * @method static Builder<static>|Video whereShares($value)
 * @method static Builder<static>|Video whereSizeKb($value)
 * @method static Builder<static>|Video whereStatus($value)
 * @method static Builder<static>|Video whereTags($value)
 * @method static Builder<static>|Video whereUpdatedAt($value)
 * @method static Builder<static>|Video whereUri($value)
 * @method static Builder<static>|Video whereVid($value)
 * @method static Builder<static>|Video whereVidOptimized($value)
 * @method static Builder<static>|Video whereViews($value)
 * @method static Builder<static>|Video whereVisibility($value)
 *
 * @mixin \Eloquent
 */
class Video extends Model
{
    use HasFactory, HasSnowflakePrimary, HasSyncHashtagsFromCaption, HasSyncMentionsFromCaption;

    /**
     * Status Bitmask
     * 0 = Unused
     * 1 = Pending Transcoding
     * 2 = Published
     * 3 = Reserved
     * 4 = Archived
     * 5 = Reserved
     * 6 = Admin unpublished
     * 7 = Account disabled
     * 8 = Account pending deletion
     * 9 = Pending unavailable/deleted
     **/

    /**
     * Visibility Bitmask
     * 0 = Unused
     * 1 = Public
     * 2 = Local
     * 3 = Unlisted
     * 4 = Followers only
     * 5 = Mentioned users only
     **/

    /**
     * Indicates if the IDs are auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = false;

    public $guarded = [];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'duration' => 'integer',
            'status' => 'integer',
            'profile_id' => 'string',
            'is_sensitive' => 'boolean',
            'media_metadata' => 'array',
            'is_local' => 'boolean',
            'is_edited' => 'boolean',
            'is_pinned' => 'boolean',
            'can_duet' => 'boolean',
            'can_stitch' => 'boolean',
            'can_download' => 'boolean',
            'can_comment' => 'boolean',
            'bookmarks' => 'integer',
            'contains_ai' => 'boolean',
            'contains_ad' => 'boolean',
            'federated_at' => 'datetime',
            'audio_allow_reuse' => 'boolean',
            'has_hidden_comments' => 'boolean',
        ];
    }

    /**
     * @param  Builder<Video>  $query
     * @return Builder<Video>
     */
    protected function scopePublished(Builder $query): Builder
    {
        return $query->where('status', 2);
    }

    #[Scope]
    protected function canComment(Builder $query): void
    {
        $query->where('comment_state', 4);
    }

    public function statusLabel()
    {
        return match ($this->status) {
            1 => 'pending',
            2 => 'published',
            4 => 'archived',
            6 => 'unpublished',
            default => null
        };
    }

    public function hashid()
    {
        return HashidService::encode($this->id);
    }

    public function thumb()
    {
        $thumb = url('/storage/videos/video-placeholder.jpg');
        if ($this->has_thumb) {
            $ext = pathinfo($this->vid, PATHINFO_EXTENSION);
            $url = str_replace('.'.$ext, '.jpg', $this->vid);
            $thumb = Storage::disk('s3')->url($url);
        }

        return $thumb;
    }

    public function getObjectUrl($suffix = null): string
    {
        return $this->is_local ?
            url('/ap/users/'.$this->profile_id.'/video/'.$this->id.$suffix) :
            $this->uri;
    }

    public function permalink($suffix = null): string
    {
        return $this->is_local ?
            url('/ap/users/'.$this->profile_id.'/video/'.$this->id.$suffix) :
            $this->uri;
    }

    /** @return BelongsTo<Profile, $this> */
    public function profile(): BelongsTo
    {
        return $this->belongsTo(Profile::class);
    }

    public function shareUrl(): string
    {
        return url('/v/'.HashidService::encode((string) $this->id));
    }

    public function mediaUrl(): string
    {
        return Storage::disk('s3')
            ->url(
                $this->has_processed && $this->vid_optimized ?
                    $this->vid_optimized :
                    $this->vid
            );
    }

    public function recalculateCommentsCount(): int
    {
        $actualCount = DB::selectOne("
            SELECT (
                (SELECT COUNT(*) FROM comments
                 WHERE video_id = ?)
                +
                (SELECT COUNT(*) FROM comment_replies
                 WHERE video_id = ? AND status = 'active' AND deleted_at IS NULL)
            ) as total_count
        ", [$this->id, $this->id])->total_count;

        $this->update(['comments' => $actualCount]);

        return $actualCount;
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany<Hashtag, $this, VideoHashtag, 'pivot'>
     */
    public function hashtags()
    {
        return $this->belongsToMany(Hashtag::class, 'video_hashtags')
            ->using(VideoHashtag::class);
    }

    /** @return MorphMany<Mention, $this> */
    public function mentions(): MorphMany
    {
        return $this->morphMany(Mention::class, 'mentionable');
    }

    /** @return HasMany<VideoCaptionEdit, $this> */
    public function edits(): HasMany
    {
        return $this->hasMany(VideoCaptionEdit::class);
    }

    /** @return BelongsToMany<Playlist, $this> */
    public function playlists(): BelongsToMany
    {
        return $this->belongsToMany(Playlist::class)
            ->withPivot('position')
            ->withTimestamps();
    }

    public function playlist(): ?Playlist
    {
        return $this->playlists()->first();
    }

    public function quoteAuthorizations(): MorphMany
    {
        return $this->morphMany(QuoteAuthorization::class, 'quotable');
    }
}
