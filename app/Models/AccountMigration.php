<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $uuid
 * @property string $direction
 * @property string $state
 * @property int $profile_id
 * @property int|null $remote_profile_id
 * @property string $local_actor
 * @property string $remote_actor
 * @property string $remote_domain
 * @property string|null $remote_migration_id
 * @property string|null $remote_migration_hash
 * @property array<int, string>|null $scope
 * @property string|null $manifest_root
 * @property int $manifest_items
 * @property int $manifest_bytes
 * @property string|null $map_root
 * @property string|null $intent_jws
 * @property string|null $receipt_jws
 * @property array<string, mixed>|null $seal
 * @property string|null $transit_url
 * @property int $items_total
 * @property int $items_done
 * @property int $items_failed
 * @property int $items_skipped
 * @property int $bytes_done
 * @property array<string, mixed>|null $stats
 * @property string|null $error
 * @property \Illuminate\Support\Carbon|null $offered_at
 * @property \Illuminate\Support\Carbon|null $accepted_at
 * @property \Illuminate\Support\Carbon|null $completed_at
 * @property \Illuminate\Support\Carbon|null $finalized_at
 * @property \Illuminate\Support\Carbon|null $cancelled_at
 * @property \Illuminate\Support\Carbon|null $failed_at
 * @property \Illuminate\Support\Carbon|null $expires_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Profile|null $profile
 * @property-read Profile|null $remoteProfile
 * @property-read \Illuminate\Database\Eloquent\Collection<int, AccountMigrationItem> $items
 * @property-read int|null $items_count
 *
 * @method static Builder<static>|AccountMigration outbound()
 * @method static Builder<static>|AccountMigration inbound()
 * @method static Builder<static>|AccountMigration active()
 *
 * @mixin \Eloquent
 */
class AccountMigration extends Model
{
    public const OUTBOUND = 'outbound';

    public const INBOUND = 'inbound';

    public const STATE_BUILDING = 'building';

    public const STATE_OFFERED = 'offered';

    public const STATE_PENDING = 'pending';

    public const STATE_TRANSFERRING = 'transferring';

    public const STATE_SEALING = 'sealing';

    public const STATE_READY = 'ready';

    public const STATE_FINALIZING = 'finalizing';

    public const STATE_FINALIZED = 'finalized';

    public const STATE_COMPLETED = 'completed';

    public const STATE_FAILED = 'failed';

    public const STATE_CANCELLED = 'cancelled';

    public const STATE_REJECTED = 'rejected';

    public const ACTIVE_OUTBOUND = [
        self::STATE_BUILDING,
        self::STATE_OFFERED,
        self::STATE_TRANSFERRING,
        self::STATE_READY,
        self::STATE_FINALIZING,
    ];

    public const ACTIVE_INBOUND = [
        self::STATE_PENDING,
        self::STATE_TRANSFERRING,
        self::STATE_SEALING,
    ];

    public const SCOPES = ['profile', 'videos', 'playlists', 'sounds', 'following', 'blocks', 'bookmarks'];

    protected $guarded = [];

    protected $hidden = ['intent_jws', 'receipt_jws'];

    protected function casts(): array
    {
        return [
            'scope' => 'array',
            'seal' => 'array',
            'stats' => 'array',
            'manifest_items' => 'integer',
            'manifest_bytes' => 'integer',
            'items_total' => 'integer',
            'items_done' => 'integer',
            'items_failed' => 'integer',
            'items_skipped' => 'integer',
            'bytes_done' => 'integer',
            'offered_at' => 'datetime',
            'accepted_at' => 'datetime',
            'completed_at' => 'datetime',
            'finalized_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'failed_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (AccountMigration $migration) {
            if (! $migration->uuid) {
                $migration->uuid = (string) Str::uuid();
            }
        });
    }

    public function profile(): BelongsTo
    {
        return $this->belongsTo(Profile::class);
    }

    public function remoteProfile(): BelongsTo
    {
        return $this->belongsTo(Profile::class, 'remote_profile_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(AccountMigrationItem::class, 'migration_id');
    }

    protected function scopeOutbound(Builder $query): Builder
    {
        return $query->where('direction', self::OUTBOUND);
    }

    protected function scopeInbound(Builder $query): Builder
    {
        return $query->where('direction', self::INBOUND);
    }

    protected function scopeActive(Builder $query): Builder
    {
        return $query->where(function ($q) {
            $q->where(function ($o) {
                $o->where('direction', self::OUTBOUND)->whereIn('state', self::ACTIVE_OUTBOUND);
            })->orWhere(function ($i) {
                $i->where('direction', self::INBOUND)->whereIn('state', self::ACTIVE_INBOUND);
            });
        });
    }

    public function isOutbound(): bool
    {
        return $this->direction === self::OUTBOUND;
    }

    public function isInbound(): bool
    {
        return $this->direction === self::INBOUND;
    }

    public function isActive(): bool
    {
        return $this->isOutbound()
            ? in_array($this->state, self::ACTIVE_OUTBOUND, true)
            : in_array($this->state, self::ACTIVE_INBOUND, true);
    }

    public function isCancellable(): bool
    {
        return $this->isOutbound() && in_array($this->state, [
            self::STATE_BUILDING,
            self::STATE_OFFERED,
            self::STATE_TRANSFERRING,
            self::STATE_READY,
        ], true);
    }

    public function apUrl(string $suffix = ''): string
    {
        return url('/ap/migrations/'.$this->uuid.$suffix);
    }

    public function manifestUrl(?int $page = null): string
    {
        return $this->apUrl('/manifest'.($page ? '?page='.$page : ''));
    }

    public function itemUrl(int $position): string
    {
        return $this->apUrl('/items/'.$position);
    }

    public function mapUrl(?int $page = null): string
    {
        return $this->apUrl('/map'.($page ? '?page='.$page : ''));
    }

    public function offerId(): string
    {
        return $this->apUrl('#offer');
    }

    public function selectedScopes(): array
    {
        return array_values(array_intersect(self::SCOPES, (array) $this->scope));
    }

    public function hasScope(string $scope): bool
    {
        return in_array($scope, (array) $this->scope, true);
    }

    public function fail(string $error): void
    {
        $this->forceFill([
            'state' => self::STATE_FAILED,
            'error' => Str::limit($error, 1000),
            'failed_at' => now(),
        ])->save();
    }

    public function refreshCounters(): void
    {
        $counts = AccountMigrationItem::forMigration($this)
            ->selectRaw('state, count(*) as total, coalesce(sum(bytes), 0) as bytes')
            ->groupBy('state')
            ->get()
            ->keyBy('state');

        $this->forceFill([
            'items_total' => (int) $counts->sum('total'),
            'items_done' => (int) ($counts->get('done')->total ?? 0),
            'items_failed' => (int) ($counts->get('failed')->total ?? 0),
            'items_skipped' => (int) ($counts->get('skipped')->total ?? 0),
            'bytes_done' => (int) ($counts->get('done')->bytes ?? 0),
        ])->save();
    }

    public function kindCounts(): array
    {
        if ($this->isOutbound()) {
            return AccountMigrationItem::forMigration($this)
                ->selectRaw('kind, count(*) as total')
                ->groupBy('kind')
                ->pluck('total', 'kind')
                ->map(fn ($total) => ['total' => (int) $total])
                ->all();
        }

        return AccountMigrationItem::forMigration($this)
            ->selectRaw('kind, state, count(*) as total')
            ->groupBy('kind', 'state')
            ->get()
            ->groupBy('kind')
            ->map(fn ($rows) => $rows->pluck('total', 'state')->map(fn ($v) => (int) $v)->all())
            ->all();
    }

    public function toSummary(): array
    {
        return [
            'id' => $this->uuid,
            'direction' => $this->direction,
            'state' => $this->state,
            'local_actor' => $this->local_actor,
            'remote_actor' => $this->remote_actor,
            'remote_domain' => $this->remote_domain,
            'remote_account' => $this->remoteProfile ? [
                'id' => (string) $this->remoteProfile->id,
                'username' => $this->remoteProfile->username,
                'name' => $this->remoteProfile->name,
                'avatar' => $this->remoteProfile->avatar,
            ] : null,
            'scope' => $this->selectedScopes(),
            'progress' => [
                'total' => $this->items_total,
                'done' => $this->items_done,
                'failed' => $this->items_failed,
                'skipped' => $this->items_skipped,
                'bytes_total' => $this->manifest_bytes,
                'bytes_done' => $this->bytes_done,
            ],
            'kinds' => $this->kindCounts(),
            'stats' => $this->stats,
            'sealed' => ! empty($this->seal['jws'] ?? null),
            'transit_url' => $this->transit_url,
            'error' => $this->error,
            'can_cancel' => $this->isCancellable(),
            'can_finalize' => $this->isOutbound() && $this->state === self::STATE_READY,
            'offered_at' => $this->offered_at?->toIso8601String(),
            'accepted_at' => $this->accepted_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'finalized_at' => $this->finalized_at?->toIso8601String(),
            'cancelled_at' => $this->cancelled_at?->toIso8601String(),
            'failed_at' => $this->failed_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
