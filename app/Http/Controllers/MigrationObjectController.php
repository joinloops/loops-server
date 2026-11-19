<?php

namespace App\Http\Controllers;

use App\Federation\Migration;
use App\Models\AccountMigration;
use App\Models\AccountMigrationItem;
use App\Services\Migration\InboundMigrationService;
use Dedoc\Scramble\Attributes\ExcludeAllRoutesFromDocs;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

#[ExcludeAllRoutesFromDocs]
class MigrationObjectController extends Controller
{
    public const SERVABLE_OUTBOUND = [
        AccountMigration::STATE_OFFERED,
        AccountMigration::STATE_TRANSFERRING,
        AccountMigration::STATE_READY,
        AccountMigration::STATE_FINALIZING,
    ];

    public function show(Request $request, string $uuid): JsonResponse
    {
        $migration = $this->authorizedMigration($request, $uuid);

        return $this->activityPubResponse([
            '@context' => Migration::context(),
            'id' => $migration->apUrl(),
            'type' => Migration::OBJECT_TYPE,
            'state' => $migration->state,
            'attributedTo' => $migration->isOutbound() ? $migration->local_actor : $migration->remote_actor,
            'target' => $migration->isOutbound() ? $migration->remote_actor : $migration->local_actor,
            'intent' => $migration->isOutbound() ? $migration->intent_jws : null,
            'manifest' => $migration->isOutbound() ? $migration->manifestUrl() : null,
            'receipt' => $migration->isInbound() ? $migration->receipt_jws : null,
            'transit' => $migration->transit_url,
            'updated' => $migration->updated_at?->toIso8601ZuluString(),
        ]);
    }

    public function manifest(Request $request, string $uuid): JsonResponse
    {
        $migration = $this->authorizedMigration($request, $uuid, AccountMigration::OUTBOUND);
        $this->assertServable($migration);

        $pageSize = max(1, (int) config('loops.migration.manifest_page_size', 25));
        $page = (int) $request->query('page', 0);
        $total = (int) $migration->manifest_items;
        $lastPage = max(1, (int) ceil($total / $pageSize));

        if ($page < 1) {
            return $this->activityPubResponse([
                '@context' => Migration::context(),
                'id' => $migration->manifestUrl(),
                'type' => 'OrderedCollection',
                'totalItems' => $total,
                'first' => $migration->manifestUrl(1),
                'last' => $migration->manifestUrl($lastPage),
            ]);
        }

        $items = AccountMigrationItem::forMigration($migration)
            ->where('position', '>', ($page - 1) * $pageSize)
            ->where('position', '<=', $page * $pageSize)
            ->orderBy('position')
            ->get()
            ->map(fn (AccountMigrationItem $item) => $this->present($item))
            ->values()
            ->all();

        return $this->activityPubResponse([
            '@context' => Migration::context(),
            'id' => $migration->manifestUrl($page),
            'type' => 'OrderedCollectionPage',
            'partOf' => $migration->manifestUrl(),
            'totalItems' => $total,
            'next' => $page < $lastPage ? $migration->manifestUrl($page + 1) : null,
            'prev' => $page > 1 ? $migration->manifestUrl($page - 1) : null,
            'orderedItems' => $items,
        ]);
    }

    public function map(Request $request, string $uuid): JsonResponse
    {
        $migration = $this->authorizedMigration($request, $uuid, AccountMigration::INBOUND);

        if ($migration->state !== AccountMigration::STATE_COMPLETED) {
            abort(404);
        }

        $pageSize = max(1, (int) config('loops.migration.map_page_size', 200));
        $page = max(1, (int) $request->query('page', 1));
        $query = app(InboundMigrationService::class)->mapQuery($migration);
        $total = (clone $query)->count();
        $lastPage = max(1, (int) ceil($total / $pageSize));

        $entries = $query
            ->skip(($page - 1) * $pageSize)
            ->take($pageSize)
            ->get()
            ->map(fn ($row) => [
                'source' => $row->source_id,
                'target' => $row->target_id,
                'url' => $row->target_url,
            ])
            ->values()
            ->all();

        return $this->activityPubResponse([
            '@context' => Migration::context(),
            'id' => $migration->mapUrl($page),
            'type' => 'OrderedCollectionPage',
            'partOf' => $migration->mapUrl(),
            'totalItems' => $total,
            'next' => $page < $lastPage ? $migration->mapUrl($page + 1) : null,
            'orderedItems' => $entries,
        ]);
    }

    protected function authorizedMigration(Request $request, string $uuid, ?string $direction = null): AccountMigration
    {
        if (! preg_match('/^[0-9a-fA-F-]{36}$/', $uuid)) {
            abort(404);
        }

        $migration = AccountMigration::where('uuid', $uuid)->first();
        $signer = $request->attributes->get('migration_signer');

        if (! $migration || ! $signer || $signer !== $migration->remote_domain || ($direction && $migration->direction !== $direction)) {
            abort(404);
        }

        return $migration;
    }

    protected function assertServable(AccountMigration $migration): void
    {
        if (! in_array($migration->state, self::SERVABLE_OUTBOUND, true)) {
            abort(410);
        }
    }

    protected function present(AccountMigrationItem $item): array
    {
        return [
            'position' => $item->position,
            'kind' => $item->kind,
            'id' => $item->source_id,
            'data' => (array) $item->payload,
        ];
    }
}
