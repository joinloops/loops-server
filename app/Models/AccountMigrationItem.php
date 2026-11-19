<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $migration_id
 * @property int $position
 * @property string $kind
 * @property string $source_id
 * @property string $source_hash
 * @property string|null $target_id
 * @property string|null $target_url
 * @property int|null $local_id
 * @property array<string, mixed>|null $payload
 * @property string $leaf_hash
 * @property string $state
 * @property int $attempts
 * @property int $bytes
 * @property string|null $error
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read AccountMigration|null $migration
 *
 * @method static Builder<static>|AccountMigrationItem forMigration(AccountMigration $migration)
 *
 * @mixin \Eloquent
 */
class AccountMigrationItem extends Model
{
    public const KIND_PROFILE = 'profile';

    public const KIND_VIDEO = 'video';

    public const KIND_SOUND = 'sound';

    public const KIND_PLAYLIST = 'playlist';

    public const KIND_FOLLOW = 'follow';

    public const KIND_BLOCK = 'block';

    public const KIND_BOOKMARK = 'bookmark';

    public const STATE_PENDING = 'pending';

    public const STATE_DONE = 'done';

    public const STATE_SKIPPED = 'skipped';

    public const STATE_FAILED = 'failed';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'position' => 'integer',
            'attempts' => 'integer',
            'bytes' => 'integer',
            'local_id' => 'integer',
        ];
    }

    protected function scopeForMigration(Builder $query, AccountMigration $migration): Builder
    {
        return $query->where('migration_id', $migration->id);
    }

    public function migration(): BelongsTo
    {
        return $this->belongsTo(AccountMigration::class, 'migration_id');
    }

    public function markDone(?int $localId = null, ?string $targetId = null, ?string $targetUrl = null, ?int $bytes = null, ?string $error = null): void
    {
        $this->state = self::STATE_DONE;
        $this->error = $error;

        if ($localId !== null) {
            $this->local_id = $localId;
        }

        if ($targetId !== null) {
            $this->target_id = $targetId;
        }

        if ($targetUrl !== null) {
            $this->target_url = $targetUrl;
        }

        if ($bytes !== null) {
            $this->bytes = $bytes;
        }

        $this->save();
    }

    public function markSkipped(string $reason): void
    {
        $this->forceFill(['state' => self::STATE_SKIPPED, 'error' => mb_substr($reason, 0, 250)])->save();
    }

    public function markFailed(string $reason): void
    {
        $this->forceFill(['state' => self::STATE_FAILED, 'error' => mb_substr($reason, 0, 250)])->save();
    }
}
