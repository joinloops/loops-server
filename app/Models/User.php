<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Observers\UserObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Passport\Contracts\OAuthenticatable;
use Laravel\Passport\HasApiTokens;

/**
 * @property-read Profile|null $profile
 * @property-read Video|null $video
 * @property int $id
 * @property string $name
 * @property int|null $profile_id
 * @property string $username
 * @property string $email
 * @property \Illuminate\Support\Carbon|null $email_verified_at
 * @property string $password
 * @property bool $is_admin
 * @property int $can_upload
 * @property int $can_comment
 * @property int $can_like
 * @property int $can_follow
 * @property string|null $remember_token
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property string|null $push_token
 * @property array<array-key, mixed>|null $device
 * @property \Illuminate\Support\Carbon|null $push_token_verified_at
 * @property string|null $admin_note
 * @property int|null $trust_level
 * @property int $has_2fa
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_backups
 * @property string|null $delete_after
 * @property string|null $email_verification_token
 * @property string|null $last_active_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Comment> $comments
 * @property-read int|null $comments_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\DataExport> $dataExports
 * @property-read int|null $data_exports_count
 * @property-read \App\Models\UserDataSettings|null $dataSettings
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Follower> $followers
 * @property-read int|null $followers_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Follower> $following
 * @property-read int|null $following_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\VideoLike> $likes
 * @property-read int|null $likes_count
 * @property-read \Illuminate\Notifications\DatabaseNotificationCollection<int, \Illuminate\Notifications\DatabaseNotification> $notifications
 * @property-read int|null $notifications_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Laravel\Sanctum\PersonalAccessToken> $tokens
 * @property-read int|null $tokens_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\UserVideoView> $videoViews
 * @property-read int|null $video_views_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Video> $videos
 * @property-read int|null $videos_count
 *
 * @method static \Database\Factories\UserFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereAdminNote($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereCanComment($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereCanFollow($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereCanLike($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereCanUpload($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereDeleteAfter($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereDevice($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereEmailVerificationToken($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereEmailVerifiedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereHas2fa($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereIsAdmin($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereLastActiveAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User wherePassword($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereProfileId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User wherePushToken($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User wherePushTokenVerifiedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereRememberToken($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereTrustLevel($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereTwoFactorBackups($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereTwoFactorSecret($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereUsername($value)
 *
 * @mixin \Eloquent
 */
#[ObservedBy([UserObserver::class])]
class User extends Authenticatable implements OAuthenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * Status Bitmask
     * 0 = Unused
     * 1 = Active
     * 2 = Reserved
     * 3 = Reserved
     * 4 = Reserved
     * 5 = Reserved
     * 6 = Restricted by admins
     * 7 = Account disabled
     * 8 = Account pending deletion
     **/
    protected $fillable = [
        'name',
        'username',
        'email',
        'password',
        'can_upload',
        'can_follow',
        'can_comment',
        'can_like',
        'last_active_at',
        'birth_date',
        'delete_after',
        'status',
    ];

    protected $hidden = [
        'email',
        'password',
        'remember_token',
        'push_token',
        'device',
        'push_token_verified_at',
        'admin_note',
        'two_factor_secret',
        'two_factor_backups',
        'email_verification_token',
        'last_active_at',
        'birth_date',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_admin' => 'boolean',
            'device' => 'json',
            'push_token_verified_at' => 'datetime',
            'birth_date' => 'date',
            'status' => 'integer',
        ];
    }

    public function active(): bool
    {
        return $this->whereNotNull('email_verified_at')->where('status', 1)->exists();
    }

    public function profile(): HasOne
    {
        return $this->hasOne(Profile::class);
    }

    /**
     * @return HasMany<Video, $this>
     */
    public function videos(): HasMany
    {
        return $this->hasMany(Video::class, 'profile_id');
    }

    /** @return HasMany<Comment, $this> */
    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class, 'profile_id');
    }

    /** @return HasMany<VideoLike, $this> */
    public function likes(): HasMany
    {
        return $this->hasMany(VideoLike::class, 'profile_id');
    }

    /** @return HasMany<Follower, $this> */
    public function followers(): HasMany
    {
        return $this->hasMany(Follower::class, 'following_id', 'profile_id');
    }

    /** @return HasMany<Follower, $this> */
    public function following(): HasMany
    {
        return $this->hasMany(Follower::class, 'profile_id', 'profile_id');
    }

    public function videoViews(): HasMany
    {
        return $this->hasMany(UserVideoView::class);
    }

    /** @return HasMany<DataExport, $this> */
    public function dataExports(): HasMany
    {
        return $this->hasMany(DataExport::class);
    }

    /**
     * @return HasOne<UserDataSettings, $this>
     */
    public function dataSettings(): HasOne
    {
        return $this->hasOne(UserDataSettings::class);
    }

    public function getOrCreateDataSettings(): UserDataSettings
    {
        return $this->dataSettings()->firstOrCreate([
            'user_id' => $this->id,
        ]);
    }

    public function getTotalWatchTimeInHours(): int
    {
        return (int) ($this->videoViews()->sum('watch_time_seconds') / 3600);
    }

    public function getTotalDataSize(): string
    {
        $totalBytes = 0;

        $totalBytes += $this->videos()->sum('size_kb');

        if ($totalBytes) {
            $totalBytes = $totalBytes * 1024;
        }

        $commentsSize = $this->comments()->count() * 500;
        $likesSize = $this->likes()->count() * 100;

        $totalBytes += $commentsSize + $likesSize;

        return $this->formatBytes($totalBytes);
    }

    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        for ($i = 0; $bytes > 1024 && $i < 4; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, 2).' '.$units[$i];
    }
}
