<?php

namespace App\Http\Controllers\Api;

use App\Federation\Migration;
use App\Http\Controllers\Api\Traits\ApiHelpers;
use App\Http\Controllers\Controller;
use App\Models\AccountMigration;
use App\Models\User;
use App\Services\Migration\AccountAliasService;
use App\Services\Migration\MigrationException;
use App\Services\Migration\MigrationSettings;
use App\Services\Migration\OutboundMigrationService;
use Dedoc\Scramble\Attributes\ExcludeAllRoutesFromDocs;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

#[ExcludeAllRoutesFromDocs]
class AccountMigrationController extends Controller
{
    use ApiHelpers;

    public function __construct(
        protected OutboundMigrationService $outbound,
        protected AccountAliasService $aliases,
        protected MigrationSettings $settings,
    ) {}

    public function show(Request $request): JsonResponse
    {
        return $this->data($this->dashboard($request->user()));
    }

    public function addAlias(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'handle' => 'required|string|max:255',
        ]);

        try {
            $this->aliases->add($request->user()->profile, $validated['handle']);
        } catch (MigrationException $e) {
            return $this->migrationError($e);
        }

        return $this->data($this->dashboard($request->user()));
    }

    public function removeAlias(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'uri' => 'required|string|max:2048',
        ]);

        $this->aliases->remove($request->user()->profile, $validated['uri']);

        return $this->data($this->dashboard($request->user()));
    }

    public function start(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'handle' => 'required|string|max:255',
            'scope' => 'required|array|min:1',
            'scope.*' => ['string', Rule::in(AccountMigration::SCOPES)],
            'password' => 'nullable|string|max:255',
        ]);

        try {
            $this->outbound->start($request->user(), $validated['handle'], $validated['scope'], $validated['password'] ?? null);
        } catch (MigrationException $e) {
            return $this->migrationError($e);
        }

        return $this->data($this->dashboard($request->user()));
    }

    public function cancel(Request $request): JsonResponse
    {
        $migration = AccountMigration::outbound()
            ->where('profile_id', $request->user()->profile_id)
            ->whereIn('state', AccountMigration::ACTIVE_OUTBOUND)
            ->latest('id')
            ->first();

        if (! $migration) {
            return $this->error('There is no migration to cancel.', 404);
        }

        try {
            $this->outbound->cancel($migration);
        } catch (MigrationException $e) {
            return $this->migrationError($e);
        }

        return $this->data($this->dashboard($request->user()));
    }

    public function finalize(Request $request): JsonResponse
    {
        $migration = AccountMigration::outbound()
            ->where('profile_id', $request->user()->profile_id)
            ->where('state', AccountMigration::STATE_READY)
            ->latest('id')
            ->first();

        if (! $migration) {
            return $this->error('There is no migration waiting to be finalized.', 404);
        }

        try {
            $this->outbound->requestFinalize($migration);
        } catch (MigrationException $e) {
            return $this->migrationError($e);
        }

        return $this->data($this->dashboard($request->user()));
    }

    protected function dashboard(User $user): array
    {
        $profile = $user->profile;
        $movedTo = $profile->movedToProfile();

        $latest = fn (string $direction) => AccountMigration::with('remoteProfile')
            ->where('profile_id', $profile->id)
            ->where('direction', $direction)
            ->latest('id')
            ->first()?->toSummary();

        return [
            'enabled' => [
                'outbound' => $this->settings->outboundEnabled(),
                'inbound' => $this->settings->inboundEnabled(),
            ],
            'account' => [
                'actor' => $profile->getActorId(),
                'handle' => '@'.$profile->username.'@'.Migration::host(config('app.url')),
                'status' => $profile->getStatusDescription(),
                'moved_at' => $profile->moved_at?->toIso8601String(),
                'moved_to' => $movedTo ? [
                    'id' => (string) $movedTo->id,
                    'username' => $movedTo->username,
                    'name' => $movedTo->name,
                    'avatar' => $movedTo->avatar,
                    'url' => $movedTo->remote_url ?: $movedTo->uri,
                ] : null,
            ],
            'aliases' => $this->aliases->list($profile),
            'max_aliases' => Migration::MAX_ALIASES,
            'requires_password' => $this->outbound->requiresPassword($user),
            'cooldown_ends_at' => $this->outbound->cooldownEndsAt($profile)?->toIso8601String(),
            'scopes' => AccountMigration::SCOPES,
            'outbound' => $latest(AccountMigration::OUTBOUND),
            'inbound' => $latest(AccountMigration::INBOUND),
            'history' => AccountMigration::with('remoteProfile')
                ->where('profile_id', $profile->id)
                ->latest('id')
                ->limit(10)
                ->get()
                ->map(fn (AccountMigration $migration) => $migration->toSummary())
                ->values()
                ->all(),
        ];
    }

    protected function migrationError(MigrationException $e): JsonResponse
    {
        return response()->json([
            'data' => [],
            'error' => [
                'code' => $e->status,
                'reason' => $e->reason,
                'message' => $e->getMessage(),
            ],
        ], $e->status);
    }
}
