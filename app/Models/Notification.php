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
 * @property int|null $system_message_id
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
 * @property int $actor_state
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Notification whereActorState($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Notification whereSystemMessageId($value)
 *
 * @mixin \Eloquent
 */
class Notification extends Model
{
    use HasFactory;

    /**
     * Types Bitmask
     * 0-7 = TBA
     * 8 = System message info
     * 9 = System message feature
     * 10 = System message update
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
     * 24 = Reserved
     * 25 = Reserved
     * 26 = Shared your video
     * 27 = Shared your comment
     * 28 = Shared your reply
     * 29 = Reserved
     * 30 = Reserved
     * 31 - Duet your video
     **/
    public const SYSTEM_MESSAGE_INFO = 8;

    public const SYSTEM_MESSAGE_FEATURE = 9;

    public const SYSTEM_MESSAGE_UPDATE = 10;

    public const NEW_FOLLOWER = 11;

    public const NEW_VIDCOMMENT = 15;

    public const NEW_VIDCOMMENTREPLY = 16;

    public const NEW_COMMENT_REPLY = 18;

    public const VIDEO_LIKE = 21;

    public const VIDEO_COMMENT_LIKE = 22;

    public const VIDEO_COMMENT_REPLY_LIKE = 23;

    public const VIDEO_SHARE = 26;

    public const VIDEO_COMMENT_SHARE = 27;

    public const VIDEO_REPLY_SHARE = 28;

    public const DUET_YOUR_VIDEO = 31;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = ['user_id', 'type', 'video_id', 'read_at', 'profile_id', 'actor_state', 'comment_id', 'comment_reply_id', 'system_message_id', 'created_at', 'updated_at'];

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

    public static function allTypes()
    {
        return [11, 15, 16, 18, 21, 22, 23, 26, 27, 28, 31];
    }

    public static function videoLikeTypes()
    {
        return [21];
    }

    public static function videoShareTypes()
    {
        return [26];
    }

    public static function commentsTypes()
    {
        return [15, 16, 18];
    }

    public static function commentLikeTypes()
    {
        return [22, 23];
    }

    public static function commentShareTypes()
    {
        return [27, 28];
    }

    public static function activityTypes()
    {
        return [15, 16, 18, 21, 22, 23, 26, 27, 28, 31];
    }

    public static function followerTypes()
    {
        return [11];
    }

    public static function systemTypes()
    {
        return [8, 9, 10];
    }
}
