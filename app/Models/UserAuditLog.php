<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @property int $id
 * @property int $user_id
 * @property string|null $type
 * @property array<array-key, mixed>|null $value
 * @property string|null $activity_type
 * @property int|null $activity_id
 * @property string|null $ip_address
 * @property string|null $client_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Model|\Eloquent|null $activity
 * @property-read string $description
 * @property-read \App\Models\User $user
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserAuditLog forUser($userId)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserAuditLog fromIp(string $ipAddress)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserAuditLog newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserAuditLog newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserAuditLog ofType(string $type)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserAuditLog query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserAuditLog recent(int $days = 30)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserAuditLog whereActivityId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserAuditLog whereActivityType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserAuditLog whereClientId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserAuditLog whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserAuditLog whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserAuditLog whereIpAddress($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserAuditLog whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserAuditLog whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserAuditLog whereUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserAuditLog whereValue($value)
 *
 * @mixin \Eloquent
 */
class UserAuditLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'type',
        'value',
        'activity_type',
        'activity_id',
        'ip_address',
        'client_id',
    ];

    protected $casts = [
        'value' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function activity(): MorphTo
    {
        return $this->morphTo();
    }

    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }

    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeRecent($query, int $days = 30)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    public function scopeFromIp($query, string $ipAddress)
    {
        return $query->where('ip_address', $ipAddress);
    }

    public function getDescriptionAttribute(): string
    {
        $descriptions = [
            'email_changed' => 'Email address was updated',
            'email_verified' => 'Email address was verified',
            'two_factor_enabled' => 'Two-factor authentication was enabled',
            'two_factor_disabled' => 'Two-factor authentication was disabled',
            'login' => 'User logged in',
            'profile_updated' => 'Profile information was updated',
            'account_deleted' => 'Account was deleted',
            'account_deletion_requested' => 'Account deletion was requested',
            'password_reset' => 'Password was reset',
            'password_changed' => 'Password was changed',
        ];

        return $descriptions[$this->type] ?? ucfirst(str_replace('_', ' ', $this->type));
    }

    public function isSecurityRelated(): bool
    {
        $securityTypes = [
            'password_changed',
            'email_changed',
            'email_verified',
            'two_factor_enabled',
            'two_factor_disabled',
            'login',
            'password_reset',
        ];

        return in_array($this->type, $securityTypes);
    }
}
