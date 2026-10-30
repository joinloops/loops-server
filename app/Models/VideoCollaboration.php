<?php

namespace App\Models;

use App\Concerns\HasSnowflakePrimary;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $video_id
 * @property int $profile_id
 * @property int $inviter_profile_id
 * @property int $state
 * @property bool $is_local
 * @property bool $auto_approved
 * @property string|null $attestation_url
 * @property string|null $attestation_hash
 * @property \Illuminate\Support\Carbon|null $responded_at
 * @property \Illuminate\Support\Carbon|null $expires_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Video|null $video
 * @property-read Profile|null $profile
 * @property-read Profile|null $inviter
 */
class VideoCollaboration extends Model
{
    use HasSnowflakePrimary;

    public $incrementing = false;

    public const STATE_PENDING = 0;

    public const STATE_APPROVED = 1;

    public const STATE_DECLINED = 2;

    public const STATE_EXPIRED = 3;

    public const STATE_REMOVED = 4;

    public const HOLDING_STATES = [self::STATE_PENDING, self::STATE_DECLINED, self::STATE_EXPIRED];

    protected $fillable = [
        'video_id',
        'profile_id',
        'inviter_profile_id',
        'state',
        'is_local',
        'auto_approved',
        'attestation_url',
        'responded_at',
        'expires_at',
    ];

    protected static function booted(): void
    {
        static::saving(function (VideoCollaboration $collab) {
            $collab->attestation_hash = self::hashUrl($collab->attestation_url);
        });
    }

    public static function hashUrl(?string $url): ?string
    {
        return $url ? hash('sha256', $url) : null;
    }

    /**
     * @param  Builder<VideoCollaboration>  $query
     * @return Builder<VideoCollaboration>
     */
    protected function scopeForAttestation(Builder $query, string $url): Builder
    {
        return $query->where('attestation_hash', self::hashUrl($url))
            ->where('attestation_url', $url);
    }

    protected function casts(): array
    {
        return [
            'state' => 'integer',
            'is_local' => 'boolean',
            'auto_approved' => 'boolean',
            'responded_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function video(): BelongsTo
    {
        return $this->belongsTo(Video::class);
    }

    public function profile(): BelongsTo
    {
        return $this->belongsTo(Profile::class);
    }

    public function inviter(): BelongsTo
    {
        return $this->belongsTo(Profile::class, 'inviter_profile_id');
    }

    /**
     * @param  Builder<VideoCollaboration>  $query
     * @return Builder<VideoCollaboration>
     */
    protected function scopeApproved(Builder $query): Builder
    {
        return $query->where('state', self::STATE_APPROVED);
    }

    /**
     * @param  Builder<VideoCollaboration>  $query
     * @return Builder<VideoCollaboration>
     */
    protected function scopePending(Builder $query): Builder
    {
        return $query->where('state', self::STATE_PENDING);
    }

    public function isApproved(): bool
    {
        return $this->state === self::STATE_APPROVED;
    }

    public function isPending(): bool
    {
        return $this->state === self::STATE_PENDING;
    }

    public function stateName(): string
    {
        return match ($this->state) {
            self::STATE_PENDING => 'pending',
            self::STATE_APPROVED => 'approved',
            self::STATE_DECLINED => 'declined',
            self::STATE_EXPIRED => 'expired',
            self::STATE_REMOVED => 'removed',
            default => 'unknown',
        };
    }

    public function attestationUrl(): string
    {
        if (! $this->is_local) {
            return (string) $this->attestation_url;
        }

        return url("/ap/users/{$this->profile_id}/coauthor_authorizations/{$this->id}");
    }
}
