<?php

namespace App\Models;

use App\Concerns\HasSnowflakePrimary;
use App\Jobs\StarterKit\SyncStarterKitItemsJob;
use App\Observers\StarterKitObserver;
use App\Policies\StarterKitPolicy;
use App\Services\ActivityService;
use App\Services\HashidService;
use App\Services\SanitizeService;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string|null $title
 * @property string|null $slug
 * @property string|null $description
 * @property string|null $remote_url
 * @property string|null $remote_object_url
 * @property string|null $header_path
 * @property string|null $header_url
 * @property string|null $icon_path
 * @property string|null $icon_url
 * @property string|null $remote_icon_url
 * @property string|null $remote_header_url
 * @property int|null $profile_id
 * @property int $uses
 * @property int $total_reach
 * @property int $total_accounts
 * @property int $approved_accounts
 * @property int $previous_status
 * @property int $status
 * @property int $visibility
 * @property int|null $topic_hashtag_id
 * @property bool $can_federate
 * @property bool $is_popular
 * @property int $is_sensitive
 * @property bool $is_discoverable
 * @property bool $is_local
 * @property int $is_loops_only
 * @property string|null $admin_note
 * @property \Illuminate\Support\Carbon|null $admin_approved_at
 * @property \Illuminate\Support\Carbon|null $observatory_submitted_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Profile> $accounts
 * @property-read int|null $accounts_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Hashtag> $hashtags
 * @property-read int|null $hashtags_count
 * @property-read \App\Models\Profile|null $profile
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\StarterKitAccount> $starterKitAccounts
 * @property-read int|null $starter_kit_accounts_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\StarterKitTag> $starterKitTags
 * @property-read int|null $starter_kit_tags_count
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StarterKit enabled()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StarterKit federated()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StarterKit local()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StarterKit newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StarterKit newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StarterKit popular()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StarterKit public()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StarterKit query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StarterKit remote()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StarterKit whereAdminApprovedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StarterKit whereAdminNote($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StarterKit whereApprovedAccounts($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StarterKit whereCanFederate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StarterKit whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StarterKit whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StarterKit whereHeaderPath($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StarterKit whereHeaderUrl($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StarterKit whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StarterKit whereIsDiscoverable($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StarterKit whereIsLocal($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StarterKit whereIsLoopsOnly($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StarterKit whereIsPopular($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StarterKit whereIsSensitive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StarterKit whereProfileId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StarterKit whereRemoteObjectUrl($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StarterKit whereRemoteUrl($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StarterKit whereSlug($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StarterKit whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StarterKit whereTitle($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StarterKit whereTopicHashtagId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StarterKit whereTotalAccounts($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StarterKit whereTotalReach($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StarterKit whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StarterKit whereUses($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StarterKit whereVisibility($value)
 *
 * @mixin \Eloquent
 */
#[ObservedBy([StarterKitObserver::class])]
#[UsePolicy(StarterKitPolicy::class)]

class StarterKit extends Model
{
    use HasSnowflakePrimary;

    /**
     * Status Bitmask
     * 0 = Newly created
     * 1 = Unused
     * 2 = Pending
     * 3 = Unused
     * 4 = Unused
     * 5 = Suspended
     * 6 = Reserved
     * 7 = Disabled Account
     * 8 = Pending Account Delete
     * 9 = Unused
     * 10 = Public/Active
     **/

    /**
     * Visibility constants
     */
    const VISIBILITY_PRIVATE = 0;

    const VISIBILITY_PUBLIC = 1;

    const VISIBILITY_UNLISTED = 2;

    const VISIBILITY_LOCAL_AUTH = 3;

    /**
     * Indicates if the IDs are auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = false;

    protected $fillable = [
        'title',
        'slug',
        'description',
        'remote_url',
        'remote_object_url',
        'profile_id',
        'uses',
        'total_reach',
        'total_accounts',
        'approved_accounts',
        'status',
        'previous_status',
        'visibility',
        'can_federate',
        'is_popular',
        'is_discoverable',
        'is_sensitive',
        'is_local',
        'admin_note',
        'admin_approved_at',
        'observatory_submitted_at',
        'icon_path',
        'icon_url',
        'header_path',
        'header_url',
        'remote_header_url',
        'remote_icon_url',
        'disabled_message',
        'rejected_message',
        'delete_after',
        'last_fetched_at',
    ];

    protected $casts = [
        'uses' => 'integer',
        'total_reach' => 'integer',
        'total_accounts' => 'integer',
        'approved_accounts' => 'integer',
        'status' => 'integer',
        'visibility' => 'integer',
        'can_federate' => 'boolean',
        'is_popular' => 'boolean',
        'is_local' => 'boolean',
        'is_discoverable' => 'boolean',
        'is_sensitive' => 'boolean',
        'admin_approved_at' => 'datetime',
        'observatory_submitted_at' => 'datetime',
        'delete_after' => 'datetime',
        'last_fetched_at' => 'datetime',
    ];

    /**
     * Get the profile that created this starter kit.
     */
    public function profile(): BelongsTo
    {
        return $this->belongsTo(Profile::class);
    }

    /**
     * Get the starter kit accounts.
     */
    public function starterKitAccounts()
    {
        return $this->hasMany(StarterKitAccount::class);
    }

    /**
     * Get the profiles included in this starter kit.
     */
    public function accounts()
    {
        return $this->belongsToMany(Profile::class, 'starter_kit_accounts')
            ->withPivot('kit_status', 'order', 'attestation_url', 'approved_at', 'rejected_at')
            ->withTimestamps()
            ->orderBy('starter_kit_accounts.order');
    }

    public function accountsWithoutTrashed()
    {
        return $this->belongsToMany(Profile::class, 'starter_kit_accounts')
            ->withPivot('kit_status', 'order', 'attestation_url', 'approved_at', 'rejected_at')
            ->withTimestamps()
            ->wherePivotNull('deleted_at')
            ->orderBy('starter_kit_accounts.order');
    }

    public function publicUrl()
    {
        $hashid = HashidService::safeEncode($this->id) ?? $this->id;

        return url('/starter-kits/'.$hashid.'/'.$this->slug);
    }

    public function getPermalink($suffix = '')
    {
        if (! $this->is_local) {
            return $this->remote_object_url;
        }

        return url('/ap/kit/'.$this->id.$suffix);
    }

    /**
     * Get approved accounts only.
     */
    /** @return BelongsToMany<Profile, StarterKit> */
    public function approvedAccounts(): BelongsToMany
    {
        return $this->accounts()->wherePivot('kit_status', StarterKitAccount::STATUS_APPROVED);
    }

    /**
     * Get approved accounts only.
     */
    /** @return BelongsToMany<Profile, StarterKit> */
    public function approvedAccountsWithoutTrashed(): BelongsToMany
    {
        return $this->accountsWithoutTrashed()->wherePivot('kit_status', StarterKitAccount::STATUS_APPROVED);
    }

    /**
     * Get the starter kit tags.
     */
    public function starterKitTags()
    {
        return $this->hasMany(StarterKitTag::class);
    }

    /**
     * @return BelongsToMany<Hashtag, $this, Pivot, 'pivot'>
     */
    public function hashtags(): BelongsToMany
    {
        return $this->belongsToMany(Hashtag::class, 'starter_kit_tags')
            ->withPivot('status', 'order')
            ->withTimestamps()
            ->orderBy('starter_kit_tags.order');
    }

    /**
     * Scope a query to only include enabled starter kits.
     */
    public function scopeEnabled($query)
    {
        return $query->where('status', '=', 10);
    }

    public function scopeActive($query)
    {
        return $query->where('status', '=', 10)->where('approved_accounts', '>=', 1);
    }

    /**
     * Scope a query to only include public starter kits.
     */
    public function scopePublic($query)
    {
        return $query->where('visibility', self::VISIBILITY_PUBLIC);
    }

    /**
     * @param  Builder<StarterKit>  $query
     * @return Builder<StarterKit>
     */
    public function scopeActiveDiscoverable($query)
    {
        return $query->where('status', 10)->where('approved_accounts', '>=', 1)->where('is_discoverable', true);
    }

    /**
     * Scope a query to only include local starter kits.
     */
    public function scopeLocal($query)
    {
        return $query->where('is_local', true);
    }

    /**
     * Scope a query to only include remote starter kits.
     */
    public function scopeRemote($query)
    {
        return $query->where('is_local', false);
    }

    /**
     * Scope a query to only include popular starter kits.
     */
    public function scopePopular($query)
    {
        return $query->where('is_popular', true);
    }

    /**
     * Scope a query to only include federated starter kits.
     */
    public function scopeFederated($query)
    {
        return $query->where('can_federate', true);
    }

    /**
     * Increment the uses counter.
     */
    public function incrementUses()
    {
        $this->increment('uses');
    }

    /**
     * Check if the starter kit is disabled.
     */
    public function isDisabled(): bool
    {
        return $this->status === 0;
    }

    /**
     * Check if the starter kit is public.
     */
    public function isPublic(): bool
    {
        return $this->visibility === self::VISIBILITY_PUBLIC;
    }

    /**
     * Check if the starter kit is local.
     */
    public function isLocal(): bool
    {
        return $this->is_local;
    }

    public function getStatusText(): string
    {
        return match ($this->status) {
            1 => 'Unknown',
            2 => 'Pending',
            5 => 'Suspended',
            10 => 'Active',
            default => 'Pending'
        };
    }

    /**
     * Check if a profile can add accounts to this starter kit.
     *
     * @param  Profile  $requester  The profile requesting to add an account
     * @param  Profile  $target  The profile being added
     * @param  bool  $isLocal  Whether the requester is local
     */
    public function canAddAccount(Profile $requester, Profile $target, bool $isLocal = true): bool
    {
        if ($requester->id == $target->id) {
            return true;
        }

        if ($target->starter_kit_state == 0) {
            return false;
        }

        if ($target->manuallyApprovesFollowers) {
            return false;
        }

        if (UserFilter::isBlocked($requester->id, $target->id)) {
            return false;
        }

        return true;
    }

    public function syncAccountCount()
    {
        $kitId = $this->id;
        $reach = DB::table('profiles')->whereIn('id', function ($query) use ($kitId) {
            $query->select('profile_id')->from('starter_kit_accounts')->where('starter_kit_id', $kitId)->where('kit_status', 1);
        })->sum('followers');
        $total = $this->accounts()->count();
        $approved = $this->approvedAccounts()->count();
        $this->update(['approved_accounts' => $approved, 'total_accounts' => $total, 'total_reach' => $reach]);
    }

    /**
     * Approve the starter kit (admin action).
     */
    public function adminApprove()
    {
        $this->update([
            'admin_approved_at' => now(),
        ]);
    }

    public function pendingChange(): HasOne
    {
        return $this->hasOne(StarterKitPendingChange::class)->pending()->latest();
    }

    public function hasPendingChange(): bool
    {
        return $this->pendingChange()->exists();
    }

    public function deleteMedia($accept = false)
    {
        if (! $accept) {
            return;
        }

        if (Storage::disk('s3')->exists('starterkit/'.$this->id)) {
            Storage::disk('s3')->deleteDirectory('starterkit/'.$this->id);
        }
    }

    protected function extractRemoteMediaUrl($media): ?string
    {
        if (! is_array($media)) {
            return null;
        }

        $url = $media['url'] ?? null;

        if (is_string($url)) {
            return $url;
        }

        if (is_array($url) && is_string($url['href'] ?? null)) {
            return $url['href'];
        }

        if (is_string($media['href'] ?? null)) {
            return $media['href'];
        }

        return null;
    }

    public function findOrCreateFromUrl(string $url, ?array $kitData = null, bool $forceRefresh = false): ?self
    {
        $sanitize = app(SanitizeService::class);

        if ($sanitize->isLocalObject($url)) {
            $match = $sanitize->matchUrlTemplate(
                url: $url,
                templates: ['/ap/kit/{id}'],
                useAppHost: true,
                constraints: ['id' => '[0-9]+'],
            );

            if (! $match || ! isset($match['id'])) {
                return null;
            }

            return static::where('id', $match['id'])->where('is_local', true)->first();
        }

        if (! $sanitize->url($url, true)) {
            return null;
        }

        $kit = static::where('is_local', false)
            ->where(function (Builder $query) use ($url) {
                $query->where('remote_url', $url)
                    ->orWhere('remote_object_url', $url);
            })
            ->first();

        if ($kit && ! $forceRefresh) {
            return $kit;
        }

        $kitData = $kitData ?? app(ActivityService::class)->fetchRemoteActivity($url);

        if (! $kitData || ! isset($kitData['id'], $kitData['type'], $kitData['attributedTo'])) {
            return $kit;
        }

        if ($kitData['type'] !== 'FeaturedCollection') {
            return $kit;
        }

        if (! $kit) {
            $resolvedUrls = array_filter([
                $kitData['id'],
                is_string($kitData['url'] ?? null) ? $kitData['url'] : null,
            ]);

            if (! empty($resolvedUrls)) {
                $kit = static::where('is_local', false)
                    ->where(function (Builder $query) use ($resolvedUrls) {
                        $query->whereIn('remote_url', $resolvedUrls)
                            ->orWhereIn('remote_object_url', $resolvedUrls);
                    })
                    ->first();
            }

            if ($kit && ! $forceRefresh) {
                return $kit;
            }
        }

        $attributedTo = is_array($kitData['attributedTo'])
            ? data_get($kitData['attributedTo'], 'id')
            : $kitData['attributedTo'];

        if (! is_string($attributedTo) || ! $sanitize->url($attributedTo, true)) {
            return $kit;
        }

        $ownerProfile = app(Profile::class)->findOrCreateFromUrl($attributedTo);
        if (! $ownerProfile) {
            return $kit;
        }

        $remotePublicUrl = is_string(data_get($kitData, 'url')) ? data_get($kitData, 'url') : null;
        $iconUrl = $this->extractRemoteMediaUrl(data_get($kitData, 'icon'));
        $headerUrl = $this->extractRemoteMediaUrl(data_get($kitData, 'image'));
        $topicTag = data_get($kitData, 'topic.name');
        $hashtags = isset($kitData['hashtags']) && is_array($kitData['hashtags']) ? data_get($kitData, 'hashtags') : false;
        $topicTagName = $topicTag ? ltrim($topicTag, '#') : false;
        $title = $sanitize->cleanPlainText(data_get($kitData, 'name'));
        $slug = Str::slug($title, '-', 'en');

        if (! $kit) {
            /** @phpstan-ignore-next-line new.static */
            $kit = new static(['is_local' => false]);
        }

        try {
            $kit->forceFill([
                'title' => $title,
                'slug' => $slug,
                'description' => $sanitize->cleanHtmlWithSpacing(data_get($kitData, 'summary')),
                'remote_url' => $remotePublicUrl,
                'remote_object_url' => $kitData['id'],
                'profile_id' => $ownerProfile->id,
                'remote_icon_url' => $iconUrl,
                'remote_header_url' => $headerUrl,
                'is_sensitive' => (bool) data_get($kitData, 'sensitive', false),
                'is_discoverable' => (bool) data_get($kitData, 'discoverable', false),
                'is_local' => false,
                'is_loops_only' => false,
                'can_federate' => true,
                'visibility' => self::VISIBILITY_PUBLIC,
                'status' => 10,
                'uses' => 0,
                'total_accounts' => (int) data_get($kitData, 'totalItems', 0),
            ])->save();
        } catch (\Illuminate\Database\UniqueConstraintViolationException $e) {
            Log::info('StarterKit::findOrCreateFromUrl race resolved by retry', [
                'url' => $url,
                'remote_url' => $remotePublicUrl,
                'remote_object_url' => $kitData['id'],
            ]);

            return static::where('is_local', false)
                ->where(function (Builder $query) use ($remotePublicUrl, $kitData) {
                    $query->where('remote_url', $remotePublicUrl)
                        ->orWhere('remote_object_url', $kitData['id']);
                })
                ->first();
        }

        if ($topicTagName) {
            $hashtag = Hashtag::firstOrCreate(
                ['name_normalized' => strtolower($topicTagName)],
                ['name' => $topicTagName, 'can_autolink' => true]
            );

            StarterKitTag::create([
                'starter_kit_id' => $kit->id,
                'hashtag_id' => $hashtag->id,
                'status' => StarterKitTag::STATUS_APPROVED,
                'order' => 0,
            ]);
        }

        if ($hashtags) {
            $tagNames = collect($hashtags)
                ->map(fn ($tag) => ltrim($tag, '#'))
                ->filter()
                ->unique()
                ->values();

            foreach ($tagNames as $index => $name) {
                if ($name === $topicTagName) {
                    continue;
                }

                $hashtag = Hashtag::firstOrCreate(
                    ['name_normalized' => strtolower($name)],
                    ['name' => $name, 'can_autolink' => true]
                );

                StarterKitTag::create([
                    'starter_kit_id' => $kit->id,
                    'hashtag_id' => $hashtag->id,
                    'status' => StarterKitTag::STATUS_APPROVED,
                    'order' => $index,
                ]);
            }
        }

        SyncStarterKitItemsJob::dispatch($kit->id)->onQueue('activitypub-in');

        return $kit->fresh();
    }
}
