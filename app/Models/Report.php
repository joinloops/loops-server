<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * @property int $id
 * @property int|null $reporter_profile_id
 * @property int|null $reported_profile_id
 * @property int|null $reported_video_id
 * @property int|null $reported_comment_id
 * @property int|null $reported_comment_reply_id
 * @property string $report_type
 * @property array<array-key, mixed>|null $metadata
 * @property int $admin_seen
 * @property int $handled
 * @property int $is_remote
 * @property string|null $user_message
 * @property string|null $admin_notes
 * @property string|null $admin_remind_after
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property string|null $domain
 * @property-read \App\Models\Video|null $video
 *
 * @method static Builder<static>|Report againstProfile(string|int $profileId)
 * @method static Builder<static>|Report filterByStatus(?string $sort)
 * @method static Builder<static>|Report newModelQuery()
 * @method static Builder<static>|Report newQuery()
 * @method static Builder<static>|Report paginated(int $perPage = 10)
 * @method static Builder<static>|Report query()
 * @method static Builder<static>|Report search(?string $search)
 * @method static Builder<static>|Report whereAdminNotes($value)
 * @method static Builder<static>|Report whereAdminRemindAfter($value)
 * @method static Builder<static>|Report whereAdminSeen($value)
 * @method static Builder<static>|Report whereCreatedAt($value)
 * @method static Builder<static>|Report whereDomain($value)
 * @method static Builder<static>|Report whereHandled($value)
 * @method static Builder<static>|Report whereId($value)
 * @method static Builder<static>|Report whereIsRemote($value)
 * @method static Builder<static>|Report whereMetadata($value)
 * @method static Builder<static>|Report whereReportType($value)
 * @method static Builder<static>|Report whereReportedCommentId($value)
 * @method static Builder<static>|Report whereReportedCommentReplyId($value)
 * @method static Builder<static>|Report whereReportedProfileId($value)
 * @method static Builder<static>|Report whereReportedVideoId($value)
 * @method static Builder<static>|Report whereReporterProfileId($value)
 * @method static Builder<static>|Report whereUpdatedAt($value)
 * @method static Builder<static>|Report whereUserMessage($value)
 *
 * @mixin \Eloquent
 */
class Report extends Model
{
    use HasFactory;

    public $guarded = [];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'metadata' => 'array',
        ];
    }

    #[Scope]
    protected function search(Builder $query, ?string $search): Builder
    {
        if (empty($search)) {
            return $query;
        }

        if (str_starts_with($search, 'video_id:')) {
            $videoId = trim(substr($search, 9));

            return $query->join('videos', 'reports.reported_video_id', '=', 'videos.id')
                ->where('videos.id', $videoId)
                ->select('reports.*');
        }

        if (str_starts_with($search, 'reported_by:')) {
            $reporterId = trim(substr($search, 12));

            return $query->where('reporter_profile_id', $reporterId);
        }

        if (str_starts_with($search, 'reported_profile_id:')) {
            $profileId = trim(substr($search, 20));

            return $query->where('reports.reported_profile_id', $profileId)
                ->orWhereExists(function ($subQuery) use ($profileId) {
                    $subQuery->select(DB::raw(1))
                        ->from('videos')
                        ->whereColumn('videos.id', 'reports.reported_video_id')
                        ->where('videos.profile_id', $profileId);
                });
        }

        return $query->join('profiles', 'reports.reported_profile_id', '=', 'profiles.id')
            ->where('profiles.username', 'like', '%'.$search.'%')
            ->select('reports.*');
    }

    #[Scope]
    protected function filterByStatus(Builder $query, ?string $sort): Builder
    {
        if ($sort === 'all') {
            return $query;
        }

        $adminSeen = $sort === 'closed';

        return $query->whereAdminSeen($adminSeen);
    }

    #[Scope]
    protected function paginated(Builder $query, int $perPage = 10): Builder
    {
        return $query->orderByDesc('id');
    }

    #[Scope]
    protected function againstProfile(Builder $query, int|string $profileId): Builder
    {
        return $query->where('reported_profile_id', $profileId)
            ->orWhereExists(function ($subQuery) use ($profileId) {
                $subQuery->select(DB::raw(1))
                    ->from('videos')
                    ->whereColumn('videos.id', 'reports.reported_video_id')
                    ->where('videos.profile_id', $profileId);
            });
    }

    public static function totalReportsAgainstProfile(int|string $profileId): int
    {
        return self::where('reported_profile_id', $profileId)
            ->orWhereExists(function ($query) use ($profileId) {
                $query->select(DB::raw(1))
                    ->from('videos')
                    ->whereColumn('videos.id', 'reports.reported_video_id')
                    ->where('videos.profile_id', $profileId);
            })
            ->count();
    }

    public function reportEntityType()
    {
        if ($this->reported_profile_id) {
            return 'profile';
        }

        if ($this->reported_video_id) {
            return 'video';
        }

        if ($this->reported_comment_id) {
            return 'comment';
        }

        return 'Undefined Type';
    }

    public function video()
    {
        return $this->hasOne(Video::class, 'id', 'reported_video_id');
    }

    public function adminUrl()
    {
        return url('/admin/reports/show/'.$this->id);
    }
}
