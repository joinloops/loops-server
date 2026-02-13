<?php

namespace App\Models;

use App\Concerns\HasSnowflakePrimary;
use Illuminate\Database\Eloquent\Model;

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
}
