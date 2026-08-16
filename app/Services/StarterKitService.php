<?php

namespace App\Services;

use App\Http\Resources\StarterKitAccountResource;
use App\Http\Resources\StarterKitResource;
use App\Jobs\Federation\BroadcastStarterKitMembershipChange;
use App\Jobs\Federation\DeliverFeatureRequestAccept;
use App\Jobs\Federation\DeliverFeatureRequestReject;
use App\Jobs\Federation\DeliverFeatureRequestRevoke;
use App\Models\AdminSetting;
use App\Models\Hashtag;
use App\Models\Profile;
use App\Models\StarterKit;
use App\Models\StarterKitAccount;
use App\Models\StarterKitPendingChange;
use App\Models\StarterKitUse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class StarterKitService
{
    const CACHE_KEY = 'api:s:starterkits:';

    public function get($id, $flush = false)
    {
        $key = self::CACHE_KEY.$id;

        if ($flush) {
            Cache::forget($key);
        }

        return Cache::remember($key, now()->addHours(12), function () use ($id) {
            $kit = StarterKit::find($id);

            if (! $kit) {
                return [];
            }

            $accounts = $kit->approvedAccounts()->get()->map(fn ($acct) => AccountService::compact($acct->id))->values();

            return (new StarterKitResource($kit))->additional(['accounts' => $accounts])->resolve();
        });
    }

    public function getCompact($id)
    {
        $kit = self::get($id);

        if (! $kit) {
            return $kit;
        }

        unset($kit['accounts']);

        return $kit;
    }

    public function forget($id)
    {
        $keys = [
            self::CACHE_KEY.$id,
            self::CACHE_KEY.'accounts:'.$id,
            self::CACHE_KEY.'all-accounts:'.$id,
        ];
        foreach ($keys as $key) {
            Cache::forget($key);
        }

    }

    public function accounts($id, $flush = false)
    {
        $key = self::CACHE_KEY.'accounts:'.$id;

        if ($flush) {
            Cache::forget($key);
            $allKey = self::CACHE_KEY.'all-accounts:'.$id;
            Cache::forget($allKey);
        }

        return Cache::remember($key, now()->addHours(12), function () use ($id) {
            $kit = StarterKitAccount::whereStarterKitId($id)->where('kit_status', 1)->get();

            return StarterKitAccountResource::collection($kit);
        });
    }

    public function allAccounts($id, $flush = false)
    {
        $key = self::CACHE_KEY.'all-accounts:'.$id;

        if ($flush) {
            Cache::forget($key);
            $otherKey = self::CACHE_KEY.'accounts:'.$id;
            Cache::forget($otherKey);
        }

        return Cache::remember($key, now()->addHours(12), function () use ($id) {
            $kit = StarterKitAccount::whereStarterKitId($id)->get();

            return StarterKitAccountResource::collection($kit);
        });
    }

    public function getConfig($flush = false)
    {
        $key = self::CACHE_KEY.'v1-api:config';

        if ($flush) {
            Cache::forget($key);
        }

        return Cache::rememberForever($key, function () {
            $rawSettings = AdminSetting::whereIn('key', [
                'starterKits.kitCreationRequiresAdminApproval',
                'starterKits.allowAutoApproveKitsByCreatorsWithMinFollowers',
                'starterKits.autoApproveKitsByCreatorsWithMinFollowers',
                'starterKits.maxKitsPerAccount',
                'starterKits.minFollowersToCreate',
            ])->pluck('value', 'key');

            $mapping = [
                'starterKits.kitCreationRequiresAdminApproval' => 'requires_admin_approval',
                'starterKits.allowAutoApproveKitsByCreatorsWithMinFollowers' => 'auto_approve_enabled',
                'starterKits.autoApproveKitsByCreatorsWithMinFollowers' => 'auto_approve_follower_threshold',
                'starterKits.maxKitsPerAccount' => 'max_kits_allowed',
                'starterKits.minFollowersToCreate' => 'min_followers_required',
            ];

            return $rawSettings->mapWithKeys(function ($value, $key) use ($mapping) {
                $friendlyKey = $mapping[$key] ?? $key;

                $formattedValue = is_numeric($value) ? (int) $value : $value;
                if ($value === 'true' || $value === 'false') {
                    $formattedValue = filter_var($value, FILTER_VALIDATE_BOOLEAN);
                }

                return [$friendlyKey => $formattedValue];
            })->toArray();
        });
    }

    public function flushStats()
    {
        $keys = [
            self::CACHE_KEY.'v1-api:general-stats',
            self::CACHE_KEY.'v1-api:top-creators-weekly:v0',
        ];

        foreach ($keys as $key) {
            Cache::forget($key);
        }
    }

    public function flushStatsAndPopular()
    {
        $keys = [
            self::CACHE_KEY.'v1-api:general-stats',
            self::CACHE_KEY.'v1-api:top-creators-weekly:v0',
            self::CACHE_KEY.'v1-api:top-hashtags:v0',
        ];

        foreach ($keys as $key) {
            Cache::forget($key);
        }
    }

    public function getAccountStats($id, $flush = false)
    {
        $key = self::CACHE_KEY.'v1-api:account-stats:'.$id;

        if ($flush) {
            Cache::forget($key);
        }

        return Cache::remember($key, 86400, function () use ($id) {
            return [
                'total_kits' => StarterKit::whereProfileId($id)->whereStatus(10)->count(),
                'total_joined' => StarterKitAccount::where('profile_id', $id)
                    ->whereKitStatus(1)
                    ->whereHas('starterKit', function ($q) {
                        $q->where('status', 10);
                    })
                    ->count(),
                'total_uses' => StarterKitUse::join(
                    'starter_kits',
                    'starter_kit_uses.starter_kit_id',
                    '=',
                    'starter_kits.id'
                )->where('starter_kits.profile_id', $id)->count(),
                'total_accounts' => StarterKitAccount::join(
                    'starter_kits',
                    'starter_kit_accounts.starter_kit_id',
                    '=',
                    'starter_kits.id'
                )->where('starter_kits.profile_id', $id)
                    ->whereKitStatus(1)
                    ->count(),
            ];
        });
    }

    public function getStats($flush = false)
    {
        $key = self::CACHE_KEY.'v1-api:general-stats';

        if ($flush) {
            Cache::forget($key);
        }

        return Cache::remember($key, 86400, function () {
            return [
                'total_kits' => StarterKit::active()->count(),
                'total_uses' => StarterKitUse::count(),
                'total_accounts' => StarterKitAccount::whereKitStatus(1)->count(),
            ];
        });
    }

    public function getPopularHashtags($flush = false)
    {
        $key = self::CACHE_KEY.'v1-api:top-hashtags:v0';

        if ($flush) {
            Cache::forget($key);
        }

        return Cache::remember($key, 43200, function () {
            /** @var \Illuminate\Database\Eloquent\Collection<int, Hashtag&object{kits_count: int|string}> $hashtags */
            $hashtags = Hashtag::select('hashtags.*')
                ->selectRaw('COUNT(starter_kit_tags.starter_kit_id) as kits_count')
                ->join('starter_kit_tags', 'hashtags.id', '=', 'starter_kit_tags.hashtag_id')
                ->join('starter_kits', 'starter_kit_tags.starter_kit_id', '=', 'starter_kits.id')
                ->where('starter_kits.status', 10)
                ->where('starter_kits.is_discoverable', true)
                ->groupBy('hashtags.id')
                ->orderByDesc('kits_count')
                ->limit(20)
                ->get();

            return $hashtags
                ->map(static fn ($tag): array => [
                    'id' => (int) $tag->id,
                    'name' => (string) $tag->name_normalized,
                    'count' => (int) $tag->kits_count,
                ])
                ->values()
                ->all();
        });
    }

    public function getTopCreatorsWeekly($flush = false)
    {
        $key = self::CACHE_KEY.'v1-api:top-creators-weekly:v0';

        if ($flush) {
            Cache::forget($key);
        }

        $cached = Cache::remember($key, 43200, function () {
            return StarterKitAccount::where('kit_status', 1)
                ->select('profile_id', DB::raw('COUNT(*) as total'))
                ->groupBy('profile_id')
                ->orderByDesc('total')
                ->limit(20)
                ->get()
            // @phpstan-ignore-next-line
                ->map(fn ($row) => [
                    'profile_id' => $row->profile_id,
                    // @phpstan-ignore-next-line
                    'total_kits' => $row->total,
                ])
                ->toArray();
        });

        return collect($cached)
            ->map(function ($row) {
                $account = AccountService::compact($row['profile_id']);
                if (! $account) {
                    return;
                }
                $account['total_kits'] = $row['total_kits'];

                return $account;
            })
            ->filter()
            ->take(5)
            ->values()
            ->toArray();
    }

    /**
     * Submit a starter kit to the Loops Observatory for public discovery.
     */
    public function submitToObservatory(StarterKit $starterKit): bool
    {
        if ($starterKit->status !== 10 || ! $starterKit->is_discoverable || ! $starterKit->getPermalink()) {
            return false;
        }

        try {
            $response = Http::withHeaders([
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
                'User-Agent' => app('user_agent'),
            ])
                ->timeout(15)
                ->retry(3, sleepMilliseconds: 1000, when: fn ($e) => ! $e instanceof \Illuminate\Http\Client\RequestException)
                ->post(app('observatory').'/api/starter-kits/submit', [
                    'url' => $starterKit->getPermalink(),
                ]);

            if ($response->successful()) {
                $starterKit->update(['observatory_submitted_at' => now()]);

                return true;
            }

            Log::warning('Observatory submission failed', [
                'kit_id' => $starterKit->id,
                'url' => $starterKit->getPermalink(),
                'status' => $response->status(),
                'body' => $response->json(),
            ]);

            return false;
        } catch (\Exception $e) {
            Log::error('Observatory submission error', [
                'kit_id' => $starterKit->id,
                'url' => $starterKit->getPermalink(),
                'message' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Notify the Loops Observatory that a starter kit has been deleted.
     */
    public function submitDeletionToObservatory(StarterKit $starterKit): bool
    {
        $permalink = $starterKit->getPermalink();

        if (! $permalink) {
            return false;
        }

        try {
            $response = Http::withHeaders([
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
                'User-Agent' => app('user_agent'),
            ])
                ->timeout(15)
                ->retry(3, sleepMilliseconds: 1000, when: fn ($e) => ! $e instanceof \Illuminate\Http\Client\RequestException)
                ->delete(app('observatory').'/api/starter-kits', [
                    'url' => $permalink,
                ]);

            if ($response->successful()) {
                return true;
            }

            if ($response->status() === 404) {
                return true;
            }

            Log::warning('Observatory deletion failed', [
                'kit_id' => $starterKit->id,
                'url' => $permalink,
                'status' => $response->status(),
                'body' => $response->json(),
            ]);

            return false;
        } catch (\Exception $e) {
            Log::error('Observatory deletion error', [
                'kit_id' => $starterKit->id,
                'url' => $permalink,
                'message' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Push an update for an existing kit to the Observatory.
     * Call this when title, description, topic, or member count changes.
     */
    public function submitUpdateToObservatory(StarterKit $starterKit): bool
    {
        if (! $starterKit->observatory_submitted_at || ! $starterKit->getPermalink()) {
            return $this->submitToObservatory($starterKit);
        }

        if (! $starterKit->is_discoverable) {
            return false;
        }

        try {
            $response = Http::withHeaders([
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
                'User-Agent' => app('user_agent'),
            ])
                ->timeout(15)
                ->retry(3, sleepMilliseconds: 1000, when: fn ($e) => ! $e instanceof \Illuminate\Http\Client\RequestException)
                ->patch(app('observatory').'/api/starter-kits', [
                    'url' => $starterKit->getPermalink(),
                ]);

            if ($response->successful()) {
                $starterKit->update(['observatory_submitted_at' => now()]);

                return true;
            }

            if ($response->status() === 404) {
                return $this->submitToObservatory($starterKit);
            }

            Log::warning('Observatory update failed', [
                'kit_id' => $starterKit->id,
                'url' => $starterKit->getPermalink(),
                'status' => $response->status(),
                'body' => $response->json(),
            ]);

            return false;
        } catch (\Exception $e) {
            Log::error('Observatory update error', [
                'kit_id' => $starterKit->id,
                'url' => $starterKit->getPermalink(),
                'message' => $e->getMessage(),
            ]);

            return false;
        }
    }

    public function unlinkHeaderMedia(StarterKit $kit)
    {
        if (Storage::disk('s3')->exists($kit->header_path)) {
            Storage::disk('s3')->delete($kit->header_path);
        }
    }

    public function unlinkIconMedia(StarterKit $kit)
    {
        if (Storage::disk('s3')->exists($kit->icon_path)) {
            Storage::disk('s3')->delete($kit->icon_path);
        }
    }

    public function handleAccept(StarterKit $starterKit, StarterKitAccount $account): void
    {
        if ($starterKit->is_local) {
            NotificationService::starterKitAccountApproved($starterKit->profile_id, $account->profile_id, $starterKit->id);
            self::getAccountStats($starterKit->profile_id, true);

            BroadcastStarterKitMembershipChange::dispatch($starterKit, $account, 'Add')
                ->onQueue('activitypub-out')
                ->delay(now()->addSeconds(3));
        } else {
            $this->dispatchFeatureRequestResponse($account, 'accept');
        }

        NotificationService::starterKitAddAccountDelete($account->profile_id, $starterKit->profile_id, $starterKit->id);
        self::forget($starterKit->id);
    }

    public function handleReject(StarterKit $starterKit, StarterKitAccount $account): void
    {
        if ($starterKit->is_local) {
            NotificationService::starterKitAccountRejected($starterKit->profile_id, $account->profile_id, $starterKit->id);
        } else {
            $this->dispatchFeatureRequestResponse($account, 'reject');
        }

        NotificationService::starterKitAddAccountDelete($account->profile_id, $starterKit->profile_id, $starterKit->id);
        self::forget($starterKit->id);
    }

    private function dispatchFeatureRequestResponse(StarterKitAccount $account, string $type): void
    {
        $featureRequestId = $account->remote_object_id;

        if (! $featureRequestId) {
            Log::warning("Cannot federate FeatureRequest {$type} — missing remote_object_id", [
                'account_id' => $account->id,
            ]);

            return;
        }

        $actor = $account->profile;

        if (! $actor instanceof Profile) {
            Log::warning("Cannot federate FeatureRequest {$type} — missing actor profile", [
                'account_id' => $account->id,
            ]);

            return;
        }

        if ($type === 'accept') {
            DeliverFeatureRequestAccept::dispatch($actor, $account, $featureRequestId)
                ->onQueue('activitypub-out');
        } else {
            DeliverFeatureRequestReject::dispatch($actor, $account, $featureRequestId)
                ->onQueue('activitypub-out');
        }
    }

    public function handleRevoke(StarterKit $starterKit, StarterKitAccount $account): void
    {
        if ($starterKit->is_local) {
            NotificationService::starterKitAccountRejected($starterKit->profile_id, $account->profile_id, $starterKit->id);
            self::getAccountStats($starterKit->profile_id, true);
            BroadcastStarterKitMembershipChange::dispatch($starterKit, $account, 'Remove')
                ->onQueue('activitypub-out')
                ->delay(now()->addSeconds(3));
        } else {
            $this->dispatchFeatureRequestRevoke($account, $starterKit);
        }

        NotificationService::starterKitAddAccountDelete($account->profile_id, $starterKit->profile_id, $starterKit->id);
        self::forget($starterKit->id);
    }

    private function dispatchFeatureRequestRevoke(StarterKitAccount $account, StarterKit $kit): void
    {
        $actor = $account->profile;
        $recipient = $kit->profile;
        $attestationUrl = $account->getAttestationUrl();
        $kitUrl = $kit->getPermalink();

        if (! $actor instanceof Profile) {
            Log::warning('Cannot federate Revoke — missing actor profile', ['account_id' => $account->id]);

            return;
        }

        if (! $recipient instanceof Profile) {
            Log::warning('Cannot federate Revoke — missing recipient profile', ['account_id' => $account->id]);

            return;
        }

        if (! $attestationUrl || ! $kitUrl) {
            Log::warning('Cannot federate Revoke — missing attestation or kit URL', [
                'account_id' => $account->id,
                'has_attestation' => (bool) $attestationUrl,
                'has_kit_url' => (bool) $kitUrl,
            ]);

            return;
        }

        DeliverFeatureRequestRevoke::dispatch(
            $actor,
            $recipient,
            $attestationUrl,
            $kitUrl,
            (string) $account->id,
        )->onQueue('activitypub-out');
    }

    public function applyBundledPendingChanges(StarterKit $starterKit): void
    {
        $pending = StarterKitPendingChange::where('starter_kit_id', $starterKit->id)
            ->where('bundled_with_kit_review', true)
            ->where('status', 'pending')
            ->get();

        foreach ($pending as $change) {
            foreach ($change->changeset as $field => $data) {
                if (! in_array($field, ['icon_path', 'header_path'])) {
                    continue;
                }

                $urlField = str_replace('_path', '_url', $field);

                $publicPath = app(StarterKitPendingChangeService::class)->moveToPermanent($data['value'], $field, $starterKit->id);
                $publicUrl = Storage::disk('s3')->url($publicPath);
                $starterKit->$field = $publicPath;
                $starterKit->$urlField = $publicUrl;
            }

            $change->update(['status' => 'applied']);
        }

        $starterKit->save();
        $this->forget($starterKit->id);
    }
}
