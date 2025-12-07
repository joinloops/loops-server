<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $type
 * @property int $user_id
 * @property int|null $profile_id
 * @property int|null $video_id
 * @property int|null $comment_id
 * @property int|null $comment_reply_id
 * @property array<array-key, mixed>|null $meta
 * @property \Illuminate\Support\Carbon|null $read_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Profile|null $profile
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Notification newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Notification newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Notification query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Notification whereCommentId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Notification whereCommentReplyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Notification whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Notification whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Notification whereMeta($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Notification whereProfileId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Notification whereReadAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Notification whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Notification whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Notification whereUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Notification whereVideoId($value)
 *
 * @mixin \Eloquent
 */
class Notification extends Model
{
    use HasFactory;

    /**
     * Types Bitmask
     * 0-10 = TBA
     * 11 = New Follower
     * 12 = Reserved
     * 13 = Someone you know joined Loops
     * 14 = Your account is getting noticed
     * 15 = New Video Comment
     * 16 = New Video Comment Reply
     * 17 = Reserved
     * 18 = New Comment Reply (When not owner of video_id)
     * 19 = Reserved
     * 20 = Reserved
     * 21 = Video Like
     * 22 = Comment Like
     * 23 = Comment Reply Like
     * 24 - 30 = Reserved
     * 31 - Duet your video
     **/
    public const NEW_FOLLOWER = 11;

    public const NEW_VIDCOMMENT = 15;

    public const NEW_VIDCOMMENTREPLY = 16;

    public const NEW_COMMENT_REPLY = 18;

    public const VIDEO_LIKE = 21;

    public const VIDEO_COMMENT_LIKE = 22;

    public const VIDEO_COMMENT_REPLY_LIKE = 23;

    public const DUET_YOUR_VIDEO = 31;

    protected $fillable = ['user_id', 'type', 'video_id', 'read_at', 'profile_id', 'comment_id', 'comment_reply_id'];

    protected function casts(): array
    {
        return [
            'read_at' => 'datetime',
            'meta' => 'json',
        ];
    }

    public function profile()
    {
        return $this->belongsTo(Profile::class);
    }
}
