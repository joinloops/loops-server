<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $domain
 * @property string|null $description
 * @property int|null $user_count
 * @property int|null $video_count
 * @property int|null $comment_count
 * @property int|null $reply_count
 * @property int|null $follower_count
 * @property int|null $report_count
 * @property string|null $software
 * @property string|null $version
 * @property bool $is_blocked
 * @property bool $is_silenced
 * @property int $federation_state
 * @property string|null $admin_notes
 * @property bool $allow_video_posts
 * @property bool $allow_videos_in_fyf
 * @property \Illuminate\Support\Carbon|null $last_contacted_at
 * @property \Illuminate\Support\Carbon|null $last_failure_at
 * @property string|null $version_last_checked_at
 * @property string|null $instance_last_crawled_at
 * @property int $failure_count
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $stats_last_collected_at
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Instance newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Instance newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Instance query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Instance whereAdminNotes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Instance whereAllowVideoPosts($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Instance whereAllowVideosInFyf($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Instance whereCommentCount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Instance whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Instance whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Instance whereDomain($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Instance whereFailureCount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Instance whereFederationState($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Instance whereFollowerCount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Instance whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Instance whereInstanceLastCrawledAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Instance whereIsBlocked($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Instance whereIsSilenced($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Instance whereLastContactedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Instance whereLastFailureAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Instance whereReplyCount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Instance whereReportCount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Instance whereSoftware($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Instance whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Instance whereUserCount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Instance whereVersion($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Instance whereVersionLastCheckedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Instance whereVideoCount($value)
 *
 * @mixin \Eloquent
 */
class Instance extends Model
{
    protected $fillable = [
        'domain',
        'software',
        'version',
        'is_blocked',
        'is_silenced',
        'user_count',
        'video_count',
        'follower_count',
        'comment_count',
        'report_count',
        'reply_count',
        'last_contacted_at',
        'last_failure_at',
        'failure_count',
        'federation_state',
        'instance_last_crawled_at',
        'version_last_checked_at',
        'description',
        'admin_notes',
        'allow_video_posts',
        'allow_videos_in_fyf',
        'stats_last_collected_at',
    ];

    protected $casts = [
        'allow_video_posts' => 'boolean',
        'allow_videos_in_fyf' => 'boolean',
        'is_blocked' => 'boolean',
        'is_silenced' => 'boolean',
        'last_contacted_at' => 'datetime',
        'last_failure_at' => 'datetime',
        'failure_count' => 'integer',
        'federation_state' => 'integer',
        'stats_last_collected_at' => 'datetime',
    ];

    /* Federation State
     *
     * 0 - Reserved
     * 1 - Permanent Blocking
     * 2 - Temporary Blocking
     * 3 - Silenced
     * 4 - Temporary Silenced
     * 5 - Full federation
     */

    /**
     * @param  Builder<Instance>  $query
     * @return Builder<Instance>
     */
    protected function scopeActive(Builder $query): Builder
    {
        return $query->where('federation_state', 5);
    }
}
