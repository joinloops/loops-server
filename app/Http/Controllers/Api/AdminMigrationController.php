<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Traits\ApiHelpers;
use App\Http\Controllers\Controller;
use App\Models\AccountMigration;
use App\Models\AccountMigrationItem;
use App\Services\AdminAuditLogService;
use App\Services\Migration\AttestationService;
use App\Services\Migration\MigrationException;
use App\Services\Migration\MigrationSettings;
use App\Services\Migration\OutboundMigrationService;
use Dedoc\Scramble\Attributes\ExcludeAllRoutesFromDocs;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

#[ExcludeAllRoutesFromDocs]
class AdminMigrationController extends Controller
{
    use ApiHelpers;

    public function __construct(protected MigrationSettings $settings) {}

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'direction' => ['nullable', Rule::in([AccountMigration::OUTBOUND, AccountMigration::INBOUND])],
            'state' => 'nullable|string|max:24',
            'q' => 'nullable|string|max:120',
        ]);

        $query = AccountMigration::with(['profile:id,username,name,avatar', 'remoteProfile:id,username,name,avatar'])
            ->orderByDesc('id');

        if (! empty($validated['direction'])) {
            $query->where('direction', $validated['direction']);
        }

        if (! empty($validated['state'])) {
            $validated['state'] === 'active' ? $query->active() : $query->where('state', $validated['state']);
        }

        if (! empty($validated['q'])) {
            $q = $validated['q'];
            $query->where(function ($builder) use ($q) {
                $builder->where('remote_domain', 'like', '%'.$q.'%')
                    ->orWhere('uuid', $q)
                    ->orWhereHas('profile', fn ($p) => $p->where('username', 'like', '%'.$q.'%'));
            });
        }

        $page = $query->cursorPaginate(20)->withQueryString();

        return response()->json([
            'data' => collect($page->items())->map(fn (AccountMigration $migration) => $this->row($migration))->values(),
            'meta' => [
                'next_cursor' => $page->nextCursor()?->encode(),
                'prev_cursor' => $page->previousCursor()?->encode(),
            ],
        ]);
    }

    public function stats(): JsonResponse
    {
        $counts = AccountMigration::select('direction', 'state', DB::raw('count(*) as total'))
            ->groupBy('direction', 'state')
            ->get();

        $recent = AccountMigration::where('created_at', '>', now()->subDays(30));

        return $this->data([
            'outbound' => $counts->where('direction', AccountMigration::OUTBOUND)->pluck('total', 'state')->map(fn ($v) => (int) $v),
            'inbound' => $counts->where('direction', AccountMigration::INBOUND)->pluck('total', 'state')->map(fn ($v) => (int) $v),
            'active' => AccountMigration::active()->count(),
            'last_30_days' => [
                'outbound' => (clone $recent)->where('direction', AccountMigration::OUTBOUND)->count(),
                'inbound' => (clone $recent)->where('direction', AccountMigration::INBOUND)->count(),
                'bytes_imported' => (int) (clone $recent)->where('direction', AccountMigration::INBOUND)->sum('bytes_done'),
            ],
        ]);
    }

    public function show(string $uuid): JsonResponse
    {
        $migration = AccountMigration::with(['profile', 'remoteProfile'])->where('uuid', $uuid)->firstOrFail();
        $attestation = app(AttestationService::class);

        $decode = function (?string $jws) use ($attestation) {
            if (! $jws) {
                return null;
            }

            try {
                $decoded = $attestation->decode($jws);

                return ['header' => $decoded['header'], 'payload' => $decoded['payload']];
            } catch (\Throwable $e) {
                return null;
            }
        };

        return $this->data(array_merge($this->row($migration), [
            'intent' => $decode($migration->intent_jws),
            'receipt' => $decode($migration->receipt_jws),
            'seal' => $migration->seal,
            'manifest_root' => $migration->manifest_root,
            'map_root' => $migration->map_root,
            'remote_migration_id' => $migration->remote_migration_id,
            'expires_at' => $migration->expires_at?->toIso8601String(),
        ]));
    }

    public function items(Request $request, string $uuid): JsonResponse
    {
        $validated = $request->validate([
            'kind' => ['nullable', Rule::in(['profile', 'video', 'sound', 'playlist', 'follow', 'block', 'bookmark'])],
            'state' => ['nullable', Rule::in(['pending', 'done', 'skipped', 'failed'])],
        ]);

        $migration = AccountMigration::where('uuid', $uuid)->firstOrFail();

        $query = AccountMigrationItem::forMigration($migration)->orderBy('position');

        if (! empty($validated['kind'])) {
            $query->where('kind', $validated['kind']);
        }

        if (! empty($validated['state'])) {
            $query->where('state', $validated['state']);
        }

        $page = $query->cursorPaginate(50, ['id', 'position', 'kind', 'source_id', 'target_id', 'target_url', 'local_id', 'state', 'error', 'bytes', 'attempts', 'updated_at'])->withQueryString();

        return response()->json([
            'data' => collect($page->items())->map(fn (AccountMigrationItem $item) => [
                'position' => $item->position,
                'kind' => $item->kind,
                'source_id' => $item->source_id,
                'target_id' => $item->target_id,
                'target_url' => $item->target_url,
                'local_id' => $item->local_id ? (string) $item->local_id : null,
                'state' => $item->state,
                'error' => $item->error,
                'bytes' => $item->bytes,
                'attempts' => $item->attempts,
                'updated_at' => $item->updated_at?->toIso8601String(),
            ])->values(),
            'meta' => [
                'next_cursor' => $page->nextCursor()?->encode(),
                'prev_cursor' => $page->previousCursor()?->encode(),
            ],
        ]);
    }

    public function cancel(Request $request, string $uuid): JsonResponse
    {
        $migration = AccountMigration::where('uuid', $uuid)->firstOrFail();

        if ($migration->isOutbound()) {
            try {
                app(OutboundMigrationService::class)->cancel($migration);
            } catch (MigrationException $e) {
                return $this->error($e->getMessage(), $e->status);
            }
        } elseif ($migration->isActive()) {
            $migration->fail('Cancelled by an administrator.');
        } else {
            return $this->error('This migration is not active.', 409);
        }

        app(AdminAuditLogService::class)->log($request->user(), 'admin:migration:cancel', [
            'migration' => $migration->uuid,
            'direction' => $migration->direction,
            'remote_actor' => $migration->remote_actor,
        ], $migration);

        return $this->show($uuid);
    }

    public function getSettings(): JsonResponse
    {
        return $this->data($this->settings->all());
    }

    public function updateSettings(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'outbound_enabled' => 'sometimes|boolean',
            'inbound_enabled' => 'sometimes|boolean',
            'allow_over_limits' => 'sometimes|boolean',
            'require_seal' => 'sometimes|boolean',
            'cooldown_days' => 'sometimes|integer|min:0|max:365',
            'transit_urls' => 'sometimes|array|max:5',
            'transit_urls.*' => 'string|url:https|max:255',
        ]);

        if (isset($validated['transit_urls'])) {
            $validated['transit_urls'] = array_values(array_unique(array_map(fn ($url) => rtrim($url, '/'), $validated['transit_urls'])));
        }

        $before = $this->settings->all();
        $after = $this->settings->update($validated);

        app(AdminAuditLogService::class)->log($request->user(), 'admin:migration:settings', [
            'old' => $before,
            'new' => $after,
        ]);

        return $this->data($after);
    }

    protected function row(AccountMigration $migration): array
    {
        return array_merge($migration->toSummary(), [
            'local_account' => $migration->profile ? [
                'id' => (string) $migration->profile->id,
                'username' => $migration->profile->username,
                'name' => $migration->profile->name,
                'avatar' => $migration->profile->avatar,
            ] : null,
        ]);
    }
}
