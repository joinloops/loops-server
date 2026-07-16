<?php

namespace App\Models;

use App\Concerns\HasSnowflakePrimary;
use App\Observers\ProfileObserver;
use App\Services\ActivityService;
use App\Services\AutoLinkerService;
use App\Services\SanitizeService;
use App\Services\SigningService;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

#[ObservedBy([ProfileObserver::class])]
/**
 * @property int $id
 * @property int|null $user_id
 * @property string|null $name
 * @property string $username
 * @property string|null $bio
 * @property string|null $avatar
 * @property int $status
 * @property int $followers
 * @property int $following
 * @property int $video_count
 * @property bool $local
 * @property string|null $uri
 * @property string|null $remote_url
 * @property string|null $domain
 * @property string|null $public_key
 * @property string|null $inbox_url
 * @property string|null $outbox_url
 * @property string|null $shared_inbox_url
 * @property string|null $following_url
 * @property string|null $followers_url
 * @property int $is_suspended
 * @property int $is_hidden
 * @property int $starter_kit_state
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property array<array-key, mixed>|null $links
 * @property string|null $last_fetched_at
 * @property string|null $last_fetch_failure_at
 * @property string|null $last_delivery_failure_at
 * @property int $fetch_failure_count
 * @property int $delivery_failure_count
 * @property string|null $admin_notes
 * @property int $can_upload
 * @property int $can_follow
 * @property int $can_comment
 * @property int $can_like
 * @property int $can_share
 * @property int $can_create_starter_kits
 * @property int $can_use_starter_kits
 * @property int $can_report
 * @property int $can_playlist
 * @property int $hide_ai
 * @property int $hide_sensitive
 * @property int $manuallyApprovesFollowers
 * @property-read \App\Models\User|null $user
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Video> $videos
 * @property-read int|null $videos_count
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Profile newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Profile newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Profile query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Profile whereAdminNotes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Profile whereAvatar($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Profile whereBio($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Profile whereCanComment($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Profile whereCanFollow($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Profile whereCanLike($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Profile whereCanShare($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Profile whereCanUpload($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Profile whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Profile whereDeliveryFailureCount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Profile whereDomain($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Profile whereFetchFailureCount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Profile whereFollowers($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Profile whereFollowersUrl($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Profile whereFollowing($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Profile whereFollowingUrl($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Profile whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Profile whereInboxUrl($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Profile whereIsHidden($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Profile whereIsSuspended($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Profile whereLastDeliveryFailureAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Profile whereLastFetchFailureAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Profile whereLastFetchedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Profile whereLinks($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Profile whereLocal($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Profile whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Profile whereOutboxUrl($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Profile wherePublicKey($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Profile whereSharedInboxUrl($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Profile whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Profile whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Profile whereUri($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Profile whereUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Profile whereUsername($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Profile whereVideoCount($value)
 *
 * @property bool $discoverable
 * @property int $has_playlists
 * @property-read int|null $followers_count
 * @property-read int|null $following_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Playlist> $playlists
 * @property-read int|null $playlists_count
 * @property-read \App\Models\ProfileAvatar|null $profileAvatar
 *
 * @method static Builder<static>|Profile active()
 * @method static Builder<static>|Profile whereDiscoverable($value)
 * @method static Builder<static>|Profile whereHasPlaylists($value)
 * @method static Builder<static>|Profile whereManuallyApprovesFollowers($value)
 * @method static Builder<static>|Profile withFollowingStatus($authProfileId = null)
 *
 * @mixin \Eloquent
 */
class Profile extends Model
{
    use HasFactory, HasSnowflakePrimary;

    const STARTER_KIT_DISABLED = 0;

    const STARTER_KIT_FOLLOWING_ONLY = 1;

    const STARTER_KIT_FOLLOWING_WITH_APPROVAL = 2;

    const STARTER_KIT_LOCAL_ONLY = 3;

    const STARTER_KIT_LOCAL_WITH_APPROVAL = 4;

    const STARTER_KIT_PERMISSION_REQUIRED = 5;

    const STARTER_KIT_AUTO_ALLOW_EVERYONE = 6;

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

    /**
     * starter_kit_state Bitmask
     * 0 = Disabled (auto-reject)
     * 1 = Only accounts I follow (auto-approve)
     * 2 = Accounts I follow (manual approval)
     * 3 = Reserved
     * 4 = Reserved
     * 5 = Permission required (manual approval)
     * 6 = Auto-allow everyone (auto-approve)
     **/

    /**
     * Indicates if the IDs are auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = false;

    protected $fillable = [
        'user_id',
        'username',
        'name',
        'bio',
        'avatar',
        'followers',
        'following',
        'video_count',
        'can_upload',
        'can_follow',
        'can_comment',
        'can_like',
        'can_share',
        'manuallyApprovesFollowers',
        'domain',
        'uri',
        'remote_url',
        'inbox_url',
        'outbox_url',
        'followers_url',
        'following_url',
        'shared_inbox_url',
        'public_key',
        'last_fetched_at',
        'local',
        'discoverable',
        'status',
        'has_playlists',
        'links',
        'starter_kit_state',
        'can_create_starter_kits',
        'can_use_starter_kits',
        'can_report',
        'hide_ai',
        'hide_sensitive',
        'can_playlist',
        'can_dm',
        'enforce_ai_label',
        'enforce_ad_label',
        'enforce_nsfw_label',
    ];

    protected $guarded = [];

    protected $casts = [
        'hide_ai' => 'boolean',
        'hide_sensitive' => 'boolean',
        'status' => 'integer',
        'links' => 'array',
        'local' => 'boolean',
        'is_suspended' => 'boolean',
        'is_hidden' => 'boolean',
        'can_upload' => 'boolean',
        'can_follow' => 'boolean',
        'can_comment' => 'boolean',
        'can_share' => 'boolean',
        'can_like' => 'boolean',
        'can_playlist' => 'boolean',
        'can_create_starter_kits' => 'boolean',
        'can_use_starter_kits' => 'boolean',
        'can_report' => 'boolean',
        'can_dm' => 'boolean',
        'discoverable' => 'boolean',
        'updated_at' => 'datetime',
        'starter_kit_state' => 'integer',
        'manuallyApprovesFollowers' => 'boolean',
        'last_fetched_at' => 'datetime',
        'has_playlists' => 'boolean',
        'enforce_ai_label' => 'boolean',
        'enforce_ad_label' => 'boolean',
        'enforce_nsfw_label' => 'boolean',
    ];

    public function videos(): HasMany
    {
        return $this->hasMany(Video::class);
    }

    /**
     * @param  Builder<Profile>  $query
     * @return Builder<Profile>
     */
    protected function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 1);
    }

    public function deepLink()
    {
        $q = http_build_query([
            'avatar' => $this->avatar,
            'username' => $this->username,
            'name' => $this->name,
            'src' => 'api',
        ]);

        return 'pfloops://profile/id/'.$this->id.'?'.$q;
    }

    public function getPublicUrl()
    {
        return url('/@'.$this->username);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<Follower, $this>
     */
    public function followers()
    {
        return $this->hasMany(Follower::class, 'following_id');
    }

    /**
     * @return HasMany<Follower, $this>
     */
    public function following()
    {
        return $this->hasMany(Follower::class, 'profile_id');
    }

    public function getStatusDescription()
    {
        return match ($this->status) {
            1 => 'active',
            6 => 'suspended',
            7 => 'disabled',
            8 => 'deleted',
            default => 'Unknown',
        };
    }

    public function getActorId($suffix = null)
    {
        return $this->local ?
            url('/ap/users/'.$this->id.$suffix) :
            $this->uri.$suffix;
    }

    public function permalink($suffix = null)
    {
        return $this->local ?
            url('/ap/users/'.$this->id.$suffix) :
            $this->uri.$suffix;
    }

    public function getFollowingUrl()
    {
        return $this->local ?
            $this->getActorId('/following') :
            $this->following_url;
    }

    public function getFollowersUrl()
    {
        return $this->local ?
            $this->getActorId('/followers') :
            $this->followers_url;
    }

    public function getOutboxUrl()
    {
        return $this->local ?
            $this->getActorId('/outbox') :
            $this->outbox_url;
    }

    public function profileAvatar()
    {
        return $this->hasOne(ProfileAvatar::class);
    }

    /** @return HasMany<Playlist, $this> */
    public function playlists(): HasMany
    {
        return $this->hasMany(Playlist::class);
    }

    /** @return HasMany<ProfileLink, $this> */
    public function profileLinks()
    {
        return $this->hasMany(ProfileLink::class)->orderBy('position');
    }

    public function syncLinksJson(): void
    {
        $linksData = $this->profileLinks()
            ->orderBy('position')
            ->get()
            ->map(function ($link) {
                return [
                    'link' => $link->getUrl(),
                    'url' => $link->getVisibleLink(),
                    'is_verified' => $link->is_verified,
                ];
            })
            ->toArray();

        $this->update(['links' => $linksData]);
    }

    public function starterKitRequiresApproval(): bool
    {
        if (in_array($this->starter_kit_state, [6])) {
            return false;
        }

        return true;
    }

    /**
     * Get the actor's public key ID for HTTP signatures
     */
    public function getKeyId(): string
    {
        return $this->getActorId().'#main-key';
    }

    /**
     * Convert to ActivityPub JSON representation
     */
    public function toActivityPub(): array
    {
        if (! $this->local) {
            return [];
        }

        $res = [
            '@context' => [
                'https://www.w3.org/ns/activitystreams',
                'https://w3id.org/security/v1',
            ],
            'id' => $this->getActorId(),
            'type' => 'Person',
            'preferredUsername' => $this->username,
            'name' => $this->name,
            'summary' => $this->bio ? AutoLinkerService::link($this->bio) : '',
            'inbox' => $this->getActorId('/inbox'),
            'outbox' => $this->getActorId('/outbox'),
            'followers' => $this->getActorId('/followers'),
            'following' => $this->getActorId('/following'),
            'manuallyApprovesFollowers' => $this->manuallyApprovesFollowers,
            'url' => url('/@'.$this->username),
            'publicKey' => [
                'id' => $this->getKeyId(),
                'owner' => $this->getActorId(),
                'publicKeyPem' => app(SigningService::class)->getPublicKey(),
            ],
        ];

        if ($this->avatar) {
            $res['icon'] = [
                'type' => 'Image',
                'mediaType' => 'image/jpeg',
                'url' => $this->avatar,
            ];
        } else {
            $res['icon'] = [
                'type' => 'Image',
                'mediaType' => 'image/jpeg',
                'url' => url('/storage/avatars/default.jpg'),
            ];
        }

        if ($this->starter_kit_state) {
            $context = [
                'loops' => 'https://joinloops.org/ns#',
                'interactionPolicy' => [
                    '@id' => 'loops:interactionPolicy',
                    '@type' => '@id',
                ],
                'canFeature' => [
                    '@id' => 'loops:canFeature',
                    '@type' => '@id',
                ],
            ];

            $res['@context'][] = $context;
            $public = 'https://www.w3.org/ns/activitystreams#Public';
            $canFeature = match ($this->starter_kit_state) {
                1 => [
                    'automaticApproval' => [$this->getFollowingUrl()],
                    'manualApproval' => [$public],
                ],
                2 => [
                    'automaticApproval' => [],
                    'manualApproval' => [$this->getFollowingUrl()],
                ],
                3 => [
                    'automaticApproval' => [$this->getActorId()],
                    'manualApproval' => [],
                ],
                4 => [
                    'automaticApproval' => [$this->getActorId()],
                    'manualApproval' => [],
                ],
                5 => [
                    'automaticApproval' => [],
                    'manualApproval' => [$public],
                ],
                6 => [
                    'automaticApproval' => [$public],
                    'manualApproval' => [],
                ],
                default => [
                    'automaticApproval' => [$this->getActorId()],
                    'manualApproval' => [],
                ]
            };
            $res['interactionPolicy']['canFeature'] = $canFeature;
        }

        return $res;
    }

    /**
     * @param  array{automaticApproval?: list<string>, manualApproval?: list<string>}  $canFeature
     */
    public function resolveStarterKitState(array $canFeature): int
    {
        $auto = $canFeature['automaticApproval'] ?? [];
        $manual = $canFeature['manualApproval'] ?? [];

        $public = 'https://www.w3.org/ns/activitystreams#Public';
        $followers = $this->getFollowingUrl();
        $actor = $this->getActorId();

        return match (true) {
            $auto === [$followers] && $manual === [$public] => 1,
            $auto === [] && $manual === [$followers] => 2,
            $auto === [] && $manual === [$public] => 5,
            $auto === [$public] && $manual === [] => 6,
            $auto === [$actor] && $manual === [] => 3,
            default => 0,
        };
    }

    public function scopeWithFollowingStatus($query, $authProfileId = null)
    {
        if (! $authProfileId) {
            return $query->addSelect([
                DB::raw('0 as is_following'),
            ]);
        }

        return $query->leftJoin('followers as auth_following', function ($join) use ($authProfileId) {
            $join->on('auth_following.following_id', '=', 'profiles.id')
                ->where('auth_following.profile_id', '=', $authProfileId);
        })
            ->addSelect([
                DB::raw('CASE WHEN auth_following.id IS NOT NULL THEN 1 ELSE 0 END as is_following'),
            ]);
    }

    /**
     * Find or create a remote actor from an ActivityPub URL
     */
    public function findOrCreateFromUrl(string $url, ?array $actorData = null, $forceRefresh = false): ?self
    {
        $localUrl = app(SanitizeService::class)->isLocalObject($url);

        if ($localUrl) {
            $sanitize = app(SanitizeService::class);

            $match = $sanitize->matchUrlTemplate(
                url: $url,
                templates: [
                    '/ap/users/{id}',
                    '/@{username}',
                ],
                useAppHost: true,
                constraints: [
                    'id' => '[0-9]+',
                    'username' => '[a-zA-Z0-9_.-]+',
                ],
            );

            if (! $match) {
                return null;
            }

            if (isset($match['id'])) {
                return static::where('id', $match['id'])->where('local', true)->first();
            }

            if (isset($match['username'])) {
                return static::where('username', $match['username'])->where('local', true)->first();
            }

            return null;
        }

        $validUrl = app(SanitizeService::class)->url($url, true);

        if (! $validUrl) {
            return null;
        }

        $actor = static::where('uri', $url)->where('local', false)->first();

        if ($actor && ! $forceRefresh) {
            return $actor;
        }

        $actorData = $actorData ?? app(ActivityService::class)->fetchRemoteActor($url);

        if (! $actorData || ! isset($actorData['id'], $actorData['preferredUsername'], $actorData['inbox'], $actorData['type'])) {
            return $actor;
        }

        if ($actorData['type'] !== 'Person') {
            return $actor;
        }

        $urlHost = parse_url($url, PHP_URL_HOST);
        $idHost = parse_url($actorData['id'], PHP_URL_HOST);
        if (! $urlHost || ! $idHost || strtolower($urlHost) !== strtolower($idHost)) {
            return $actor;
        }

        $actorInbox = app(SanitizeService::class)->url($actorData['inbox'], true);
        if (! $actorInbox) {
            return $actor;
        }

        $sharedInbox = data_get($actorData, 'endpoints.sharedInbox');

        if ($sharedInbox) {
            $sharedInbox = app(SanitizeService::class)->url($sharedInbox, true);
        }

        $actorFollowers = null;
        if (isset($actorData['followers'])) {
            $actorFollowers = app(SanitizeService::class)->url($actorData['followers']);
        }

        $actorFollowing = null;
        if (isset($actorData['following'])) {
            $actorFollowing = app(SanitizeService::class)->url($actorData['following']);
        }

        $actorOutbox = null;
        if (isset($actorData['outbox'])) {
            $actorOutbox = app(SanitizeService::class)->url($actorData['outbox']);
        }

        $publicKey = null;
        if (isset($actorData['publicKey'], $actorData['publicKey']['publicKeyPem'])) {
            $publicKey = $actorData['publicKey']['publicKeyPem'];
            if (openssl_pkey_get_public($publicKey) === false) {
                return $actor;
            }
        }

        $actorId = app(SanitizeService::class)->url($actorData['id']);
        if (! $actorId) {
            return $actor;
        }

        $domain = parse_url($actorData['id'], PHP_URL_HOST);
        $username = $actorData['preferredUsername'];
        $acct = $username.'@'.$domain;
        $avatar = data_get($actorData, 'icon.url');

        if ($avatar) {
            $avatar = app(SanitizeService::class)->url($avatar);

            if (! $avatar) {
                return $actor;
            }
        }

        $remoteUrl = is_string(data_get($actorData, 'url')) ? data_get($actorData, 'url') : null;

        if ($remoteUrl) {
            $remoteUrl = app(SanitizeService::class)->url($remoteUrl);
            if (! $remoteUrl) {
                return $actor;
            }
        }

        $starterKitStateCanFeature = data_get($actorData, 'interactionPolicy.canFeature', false);
        $starterKitState = 0;

        if ($starterKitStateCanFeature) {
            $starterKitState = $this->resolveStarterKitState($starterKitStateCanFeature);
        }

        if (! $actor) {
            $actor = static::where('local', false)
                ->where(function ($q) use ($actorId, $acct) {
                    $q->where('uri', $actorId)->orWhere('username', $acct);
                })
                ->first();
        }

        if (! $actor) {
            /** @phpstan-ignore-next-line new.static */
            $actor = new static([
                'local' => false,
            ]);
        }

        $actor->forceFill([
            'username' => $acct,
            'name' => app(SanitizeService::class)->cleanPlainText($actorData['name'] ?? $username),
            'bio' => app(SanitizeService::class)->cleanHtmlWithSpacing($actorData['summary'] ?? null),
            'inbox_url' => $actorInbox,
            'avatar' => $avatar,
            'uri' => $actorId,
            'remote_url' => $remoteUrl ?? null,
            'outbox_url' => $actorOutbox ?? null,
            'followers_url' => $actorFollowers ?? null,
            'following_url' => $actorFollowing ?? null,
            'public_key' => $publicKey,
            'manuallyApprovesFollowers' => data_get($actorData, 'manuallyApprovesFollowers', false),
            'last_fetched_at' => now(),
            'local' => false,
            'domain' => $domain,
            'shared_inbox_url' => $sharedInbox ?? null,
            'starter_kit_state' => $starterKitState,
        ])->save();

        return $actor;
    }
}
