<?php

namespace App\Models;

use App\Concerns\HasSnowflakePrimary;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string|null $title
 * @property string|null $description
 * @property string $fingerprint
 * @property string $fingerprint_hash
 * @property int $status
 * @property int $duration
 * @property int|null $original_video_id
 * @property int|null $profile_id
 * @property int $usage_count
 * @property bool $is_original
 * @property bool $allow_reuse
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Video|null $originalVideo
 * @property-read \App\Models\Profile|null $profile
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Video> $videos
 * @property-read int|null $videos_count
 *
 * @method static Builder<static>|VideoSound active()
 * @method static Builder<static>|VideoSound newModelQuery()
 * @method static Builder<static>|VideoSound newQuery()
 * @method static Builder<static>|VideoSound query()
 * @method static Builder<static>|VideoSound whereAllowReuse($value)
 * @method static Builder<static>|VideoSound whereCreatedAt($value)
 * @method static Builder<static>|VideoSound whereDescription($value)
 * @method static Builder<static>|VideoSound whereDuration($value)
 * @method static Builder<static>|VideoSound whereFingerprint($value)
 * @method static Builder<static>|VideoSound whereFingerprintHash($value)
 * @method static Builder<static>|VideoSound whereId($value)
 * @method static Builder<static>|VideoSound whereIsOriginal($value)
 * @method static Builder<static>|VideoSound whereOriginalVideoId($value)
 * @method static Builder<static>|VideoSound whereProfileId($value)
 * @method static Builder<static>|VideoSound whereStatus($value)
 * @method static Builder<static>|VideoSound whereTitle($value)
 * @method static Builder<static>|VideoSound whereUpdatedAt($value)
 * @method static Builder<static>|VideoSound whereUsageCount($value)
 *
 * @mixin \Eloquent
 */
class VideoSound extends Model
{
    use HasSnowflakePrimary;

    /**
     * Indicates if the IDs are auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = false;

    protected $fillable = [
        'title',
        'description',
        'fingerprint',
        'fingerprint_hash',
        'duration',
        'status',
        'original_video_id',
        'profile_id',
        'usage_count',
        'is_original',
        'allow_reuse',
    ];

    protected $casts = [
        'is_original' => 'boolean',
        'allow_reuse' => 'boolean',
    ];

    /**
     * Boot method to auto-generate hash
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($sound) {
            if ($sound->fingerprint && ! $sound->fingerprint_hash) {
                $sound->fingerprint_hash = hash('sha256', $sound->fingerprint);
            }
        });

        static::updating(function ($sound) {
            if ($sound->isDirty('fingerprint')) {
                $sound->fingerprint_hash = hash('sha256', $sound->fingerprint);
            }
        });
    }

    /**
     * @param  Builder<VideoSound>  $query
     * @return Builder<VideoSound>
     */
    protected function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 1);
    }

    public function publicUrl()
    {
        return url('/sounds/'.$this->id);
    }

    public function originalVideo()
    {
        return $this->belongsTo(Video::class, 'original_video_id');
    }

    public function profile()
    {
        return $this->belongsTo(Profile::class);
    }

    public function videos()
    {
        return $this->hasMany(Video::class, 'sound_id');
    }

    public function incrementUsage()
    {
        $this->increment('usage_count');
    }

    public function recalculateUsage()
    {
        $videoCount = Video::whereSoundId($this->id)->count();
        $this->update(['usage_count' => $videoCount]);

        return $this;
    }
}
