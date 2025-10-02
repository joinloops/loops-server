<?php

namespace App\Models;

use App\Concerns\HasSnowflakePrimary;
use App\Observers\CommentReplyObserver;
use App\Services\HashidService;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

#[ObservedBy([CommentReplyObserver::class])]
/**
 * @property int $id
 * @property int $comment_id
 * @property int $video_id
 * @property int $profile_id
 * @property string|null $caption
 * @property array<array-key, mixed>|null $entities
 * @property int $likes
 * @property int $shares
 * @property bool $is_edited
 * @property bool $is_hidden
 * @property bool $is_sensitive
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property string $status
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property string|null $ap_id
 * @property string|null $remote_url
 * @property-read \App\Models\Comment|null $parent
 * @property-read \App\Models\Profile|null $profile
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CommentReply newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CommentReply newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CommentReply onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CommentReply query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CommentReply whereApId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CommentReply whereCaption($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CommentReply whereCommentId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CommentReply whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CommentReply whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CommentReply whereEntities($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CommentReply whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CommentReply whereIsEdited($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CommentReply whereIsHidden($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CommentReply whereIsSensitive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CommentReply whereLikes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CommentReply whereProfileId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CommentReply whereRemoteUrl($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CommentReply whereShares($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CommentReply whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CommentReply whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CommentReply whereVideoId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CommentReply withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CommentReply withoutTrashed()
 *
 * @mixin \Eloquent
 */
class CommentReply extends Model
{
    use HasFactory, HasSnowflakePrimary, SoftDeletes;

    protected $fillable = ['video_id', 'profile_id', 'comment_id', 'caption', 'status'];

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
            'is_edited' => 'boolean',
            'is_hidden' => 'boolean',
            'is_sensitive' => 'boolean',
        ];
    }

    /** @return HasOne<Comment, $this> */
    public function parent(): HasOne
    {
        return $this->hasOne(Comment::class, 'id', 'comment_id');
    }

    /** @return BelongsTo<Profile, $this> */
    public function profile(): BelongsTo
    {
        return $this->belongsTo(Profile::class);
    }

    public function getObjectUrl($suffix = null): string
    {
        return $this->ap_id ?: url('/ap/users/'.$this->profile_id.'/reply/'.$this->id.$suffix);
    }

    public function shareUrl(): string
    {
        $vid = HashidService::encode($this->video_id);
        $cid = HashidService::encode($this->id);

        return "/v/{$vid}?rid={$cid}";
    }
}
