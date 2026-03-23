<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Traits\ApiHelpers;
use App\Http\Controllers\Controller;
use App\Http\Resources\HashtagResource;
use App\Http\Resources\StarterKitAccountResource;
use App\Http\Resources\StarterKitAccountSearchResource;
use App\Http\Resources\StarterKitResource;
use App\Jobs\Federation\DeliverFollowRequest;
use App\Jobs\StarterKit\NotifyStarterKitUsersOfNewMember;
use App\Models\Follower;
use App\Models\FollowRequest;
use App\Models\Hashtag;
use App\Models\Profile;
use App\Models\StarterKit;
use App\Models\StarterKitAccount;
use App\Models\StarterKitPendingChange;
use App\Models\StarterKitTag;
use App\Models\StarterKitUse;
use App\Rules\ValidUsername;
use App\Services\AccountService;
use App\Services\AdminDashboardService;
use App\Services\ConfigService;
use App\Services\NotificationService;
use App\Services\PrivateMediaTokenService;
use App\Services\StarterKitPendingChangeService;
use App\Services\StarterKitService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Laravel\Facades\Image;

class StarterKitController extends Controller
{
    use ApiHelpers;

    public function __construct()
    {
        $this->middleware('auth');
        abort_unless(app(ConfigService::class)->starterKits(), 404);
    }

    public function accountSearch(Request $request)
    {
        $validated = $request->validate([
            'q' => [
                'required',
                new ValidUsername,
                'max:80',
            ],
        ]);

        $user = $request->user();
        $q = $validated['q'];

        $accounts = Profile::whereLocal(true)
            ->whereStatus(1)
            ->where('can_use_starter_kits', true)
            ->whereIn('starter_kit_state', [1, 2, 3, 4, 5, 6])
            ->where(function ($query) use ($q) {
                $query->where('username', 'like', $q.'%')
                    ->orWhere('name', 'like', $q.'%');
            })
            ->whereNotExists(function ($query) use ($user) {
                $query->select('id')
                    ->from('user_filters')
                    ->whereColumn('user_filters.account_id', 'profiles.id')
                    ->where('user_filters.profile_id', $user->profile_id);
            })
            ->orderByDesc('followers')
            ->cursorPaginate(12);

        return StarterKitAccountSearchResource::collection($accounts);
    }

    public function editKitAccountSearch(Request $request, $id)
    {
        $validated = $request->validate([
            'q' => [
                'required',
                new ValidUsername,
                'max:80',
            ],
        ]);

        $user = $request->user();
        $starterKit = StarterKit::whereProfileId($user->profile_id)->findOrFail($id);

        $q = $validated['q'];

        $accounts = Profile::whereLocal(true)
            ->whereStatus(1)
            ->where('can_use_starter_kits', true)
            ->whereIn('starter_kit_state', [1, 2, 3, 4, 5, 6])
            ->where(function ($query) use ($q) {
                $query->where('username', 'like', $q.'%')
                    ->orWhere('name', 'like', $q.'%');
            })
            ->whereNotExists(function ($query) use ($starterKit) {
                $query->select('id')
                    ->from('starter_kit_accounts')
                    ->whereColumn('starter_kit_accounts.profile_id', 'profiles.id')
                    ->where('starter_kit_accounts.starter_kit_id', $starterKit->id)
                    ->whereIn('starter_kit_accounts.kit_status', [2]);
            })
            ->whereNotExists(function ($query) use ($user) {
                $query->select('id')
                    ->from('user_filters')
                    ->whereColumn('user_filters.account_id', 'profiles.id')
                    ->where('user_filters.profile_id', $user->profile_id);
            })
            ->orderByDesc('followers')
            ->cursorPaginate(12);

        return StarterKitAccountSearchResource::collection($accounts);
    }

    public function hashtagSearch(Request $request)
    {
        $validated = $request->validate([
            'q' => 'required|max:80',
        ]);

        $q = $validated['q'];

        $accounts = Hashtag::where('is_nsfw', false)
            ->where('is_banned', false)
            ->where('name', 'like', $q.'%')
            ->orderByDesc('count')
            ->cursorPaginate(10);

        return HashtagResource::collection($accounts);
    }

    public function showKitAccounts(Request $request, $id)
    {
        abort_unless(app(ConfigService::class)->starterKits(), 404);

        $starterKit = StarterKit::findOrFail($id);
        $user = $request->user();

        if ($starterKit->status === 10) {
            if ($user && ($user->is_admin || $user->profile_id == $starterKit->profile_id)) {
                $accounts = StarterKitAccount::whereStarterKitId($starterKit->id)->get();
            } else {
                $accounts = StarterKitAccount::whereStarterKitId($starterKit->id)->whereKitStatus(1)->get();
            }
        } else {
            if ($user && ($user->is_admin || $user->profile_id == $starterKit->profile_id)) {
                $accounts = StarterKitAccount::whereStarterKitId($starterKit->id)->get();
            } else {
                $accounts = [];
            }
        }

        return $this->data(StarterKitAccountResource::collection($accounts));
    }

    /**
     * Store a newly created starter kit.
     */
    public function store(Request $request)
    {
        $user = $request->user();
        $profile = $user->profile;

        if ($request->user()->cannot('create', StarterKit::class)) {
            return $this->error('You are not authorized to perform this action.');
        }

        $config = app(StarterKitService::class)->getConfig();
        $adminApproval = data_get($config, 'requires_admin_approval', true);

        $validated = $request->validate([
            'title' => 'required|string|max:50',
            'description' => 'nullable|string|max:500',
            'sensitive' => 'boolean',
            'account_ids' => 'nullable|array|max:25',
            'account_ids.*' => 'exists:profiles,id',
            'hashtags' => 'nullable|array|max:10',
            'hashtags.*' => 'alpha_dash|max:40',
            'terms_of_service' => 'required|accepted',
        ]);

        $starterKit = StarterKit::create([
            'title' => $validated['title'],
            'slug' => Str::slug($validated['title'], '-', 'en'),
            'description' => $validated['description'] ?? null,
            'profile_id' => $profile->id,
            'is_discoverable' => true,
            'is_sensitive' => $validated['sensitive'] ?? false,
            'is_local' => true,
            'status' => $user->is_admin ? 10 : 0,
        ]);

        if (! empty($validated['account_ids'])) {
            foreach ($validated['account_ids'] as $index => $accountId) {
                $targetProfile = Profile::where('can_use_starter_kits', true)->find($accountId);

                if ($targetProfile && $starterKit->canAddAccount($profile, $targetProfile)) {
                    if ($profile->id == $targetProfile->id) {
                        $requiresApproval = false;
                    } else {
                        $requiresApproval = $targetProfile->starterKitRequiresApproval();
                    }

                    $approval = $requiresApproval ? StarterKitAccount::STATUS_PENDING : StarterKitAccount::STATUS_APPROVED;

                    if ($adminApproval && $approval) {
                        $approval = $profile->id == $targetProfile->id ? $approval : StarterKitAccount::STATUS_APPROVED_PENDING_ADMIN_REVIEW;
                    }

                    StarterKitAccount::create([
                        'starter_kit_id' => $starterKit->id,
                        'profile_id' => $accountId,
                        'kit_status' => $approval,
                        'order' => $index,
                        'approved_at' => $approval === 1 ? now() : null,
                    ]);

                    if (! $adminApproval) {
                        NotificationService::starterKitAddAccount($targetProfile->id, $user->profile_id, $starterKit->id);
                    }
                }
            }
        }

        if (! empty($validated['hashtags'])) {
            $tagNames = collect($validated['hashtags'])
                ->map(fn ($tag) => ltrim($tag, '#'))
                ->filter()
                ->unique()
                ->values();

            foreach ($tagNames as $index => $name) {
                $hashtag = Hashtag::firstOrCreate(
                    ['name_normalized' => strtolower($name), 'name' => $name],
                    ['can_autolink' => true]
                );

                StarterKitTag::create([
                    'starter_kit_id' => $starterKit->id,
                    'hashtag_id' => $hashtag->id,
                    'status' => StarterKitTag::STATUS_APPROVED,
                    'order' => $index,
                ]);
            }
        }

        app(StarterKitService::class)->getAccountStats($user->profile_id, true);
        app(StarterKitService::class)->submitToObservatory($starterKit->fresh());
        app(AdminDashboardService::class)->getReportsCount(true);

        $starterKit->update([
            'total_accounts' => $starterKit->starterKitAccounts()->count(),
            'approved_accounts' => $starterKit->starterKitAccounts()->approved()->count(),
        ]);

        return new StarterKitResource($starterKit);
    }

    /**
     * Update the specified starter kit.
     */
    public function update(Request $request, $id)
    {
        $user = $request->user();
        $starterKit = StarterKit::whereProfileId($user->profile_id)->findOrFail($id);

        if ($starterKit->profile_id !== $user->profile_id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        abort_if(! in_array($starterKit->status, [0, 2, 10]), 422, 'You cannot perform this action');

        $validated = $request->validate([
            'title' => 'sometimes|string|max:50',
            'description' => 'nullable|string|max:500',
            'visibility' => 'sometimes|integer|in:0,1,2',
            'is_sensitive' => 'sometimes|boolean',
            'hashtags' => 'nullable|array|max:10',
            'hashtags.*' => 'alpha_dash|max:20',
        ]);

        if (isset($validated['title'])) {
            $validated['slug'] = Str::slug($validated['title'], '-', 'en');
        }

        $hashtags = $validated['hashtags'] ?? null;
        unset($validated['hashtags']);

        if ($starterKit->is_sensitive) {
            $validated['is_sensitive'] = true;
        }

        $starterKit->update($validated);

        if ($request->has('hashtags')) {
            $this->syncHashtags($starterKit, $hashtags ?? []);
        }

        return new StarterKitResource($starterKit->fresh());
    }

    public function getKitPendingChanges(Request $request, $id)
    {
        $user = $request->user();
        $starterKit = StarterKit::whereProfileId($user->profile_id)->findOrFail($id);

        $changeset = StarterKitPendingChange::where('starter_kit_id', $starterKit->id)
            ->pending()
            ->latest()
            ->first();

        if (! $changeset) {
            return response()->json(['data' => null]);
        }

        return response()->json([
            'data' => [
                'changes' => collect($changeset->changeset)
                    ->filter(function ($field, $key) {
                        return $field['status'] === 'pending';
                    })
                    ->map(function ($field, $key) {
                        $isMedia = in_array($key, StarterKitPendingChange::MEDIA_FIELDS);

                        return [
                            'value' => $isMedia ? null : $field['value'],
                            'preview_url' => $isMedia ? $field['preview_url'] : null,
                            'action' => $field['action'] ?? null,
                            'status' => $field['status'],
                        ];
                    })
                    ->all(),
            ],
        ]);
    }

    public function getMyKits(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'limit' => 'sometimes|integer|min:1|max:10',
            'sort' => 'nullable|in:popular,latest,oldest',
            'cursor' => 'nullable|string',
        ]);

        $limit = $validated['limit'] ?? 10;

        $sort = $validated['sort'] ?? 'popular';

        $query = StarterKit::whereProfileId($user->profile_id);

        match ($sort) {
            'latest' => $query->orderByDesc('starter_kits.id'),
            'oldest' => $query->orderBy('starter_kits.id'),
            default => $query->orderByDesc('starter_kits.uses')->orderByDesc('starter_kits.id'),
        };

        $kits = $query->cursorPaginate($limit)->withQueryString();

        $stats = app(StarterKitService::class)->getAccountStats($user->profile_id);

        return StarterKitResource::collection($kits)->additional(['stats' => $stats]);
    }

    public function getJoinedKits(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'limit' => 'sometimes|integer|min:1|max:10',
            'sort' => 'nullable|in:popular,latest,oldest',
            'cursor' => 'nullable|string',
        ]);

        $limit = $validated['limit'] ?? 10;
        $sort = $validated['sort'] ?? 'latest';

        $query = StarterKit::where('status', 10)->whereHas('approvedAccounts', function ($q) use ($user) {
            $q->where('profile_id', $user->profile_id)->where('kit_status', 1);
        })
            ->with(['approvedAccounts' => function ($q) use ($user) {
                $q->where('profile_id', $user->profile_id)->where('kit_status', 1);
            }]);

        match ($sort) {
            'latest' => $query->orderByDesc('starter_kits.id'),
            'oldest' => $query->orderBy('starter_kits.id'),
            default => $query->orderByDesc('starter_kits.uses')->orderByDesc('starter_kits.id'),
        };

        $kits = $query->cursorPaginate($limit)->withQueryString();

        $stats = app(StarterKitService::class)->getAccountStats($user->profile_id);

        return StarterKitResource::collection($kits)->additional(['stats' => $stats]);
    }

    public function hasUsedKit(Request $request, $id)
    {
        $user = $request->user();
        if (! $user->can_use_starter_kits) {
            return $this->data(['used' => null, 'limit_restriction' => true]);
        }
        $pid = $user->profile_id;

        $kit = StarterKit::active()->findOrFail($id);

        $use = StarterKitUse::whereStarterKitId($kit->id)->whereProfileId($pid)->first();

        if (! $use) {
            return $this->data(['used' => false, 'delta' => null]);
        }

        $used = array_map('strval', $use->followed_profile_ids ?? []);
        $used[] = (string) $pid;

        return $this->data(['used' => true, 'delta' => $used]);
    }

    public function getConfig(Request $request)
    {
        return $this->data(app(StarterKitService::class)->getConfig());
    }

    public function getSelfConfig(Request $request)
    {
        $user = $request->user();
        if (! $user->can_create_starter_kits) {
            return $this->data([
                'total_kits' => null,
                'max_kits' => null,
                'can_create' => false,
                'limit_restriction' => true,
            ]);
        }
        $config = app(StarterKitService::class)->getConfig();
        $maxKits = data_get($config, 'max_kits_allowed', 10);
        $minFollowers = data_get($config, 'min_followers_required');
        $totalKits = StarterKit::whereProfileId($user->profile_id)->count();

        $canCreate = $maxKits > $totalKits;

        $res = [
            'total_kits' => (int) $totalKits,
            'max_kits' => (int) $maxKits,
            'can_create' => $canCreate,
        ];

        if ($user->is_admin) {
            $res['can_create'] = true;

            return $this->data($res);
        }

        if ($minFollowers) {
            $acct = app(AccountService::class)->get($user->profile_id);

            if ($acct['follower_count'] < $minFollowers) {
                $res['can_create'] = false;
                $res['min_followers_not_met'] = true;
                $res['min_followers_required'] = $minFollowers;
            }
        }

        return $this->data($res);
    }

    public function checkKitMembership(Request $request, $id)
    {
        $starterKit = StarterKit::findOrFail($id);
        $user = $request->user();
        $acct = StarterKitAccount::whereStarterKitId($starterKit->id)->whereProfileId($user->profile_id)->first();

        return $this->data([
            'in_kit' => (bool) $acct,
            'kit_status' => $acct ? $acct->kit_status : null,
        ]);
    }

    public function handleKitMembership(Request $request, $id)
    {
        $validated = $request->validate([
            'decision' => 'required|string|in:approved,rejected',
        ]);

        $starterKit = StarterKit::findOrFail($id);
        $user = $request->user();

        $acct = StarterKitAccount::whereStarterKitId($starterKit->id)
            ->whereProfileId($user->profile_id)
            ->first();

        if (! $acct) {
            return $this->error('You do not have permission for this action');
        }

        if ($acct->kit_status != 0 || $acct->approved_at || $acct->rejected_at) {
            return $this->error('You do not have permission for this action');
        }

        if ($validated['decision'] === 'approved') {
            $acct->kit_status = 1;
            $acct->approved_at = now();
            $acct->rejected_at = null;
            app(StarterKitService::class)->handleAccept($starterKit, $acct);
            NotifyStarterKitUsersOfNewMember::dispatch($starterKit, $acct)->onQueue('notify')->delay(now()->addSeconds(5));
        } else {
            $acct->kit_status = 2;
            $acct->rejected_at = now();
            $acct->approved_at = null;
            app(StarterKitService::class)->handleReject($starterKit, $acct);
        }

        NotificationService::starterKitAddAccountDelete($acct->profile_id, $starterKit->profile_id, $starterKit->id);

        app(StarterKitService::class)->getAccountStats($starterKit->profile_id, true);

        $acct->save();
        $starterKit->syncAccountCount();

        return $this->success();
    }

    public function revokeKitMembership(Request $request, $id)
    {
        $starterKit = StarterKit::findOrFail($id);
        $user = $request->user();

        $acct = StarterKitAccount::whereStarterKitId($starterKit->id)
            ->whereProfileId($user->profile_id)
            ->first();

        if (! $acct) {
            return $this->error('You do not have permission for this action');
        }

        if ($acct->kit_status === 0 || $acct->kit_status === 2) {
            return $this->error('You do not have permission for this action');
        }

        $acct->kit_status = 2;
        $acct->rejected_at = now();
        $acct->approved_at = null;
        app(StarterKitService::class)->handleReject($starterKit, $acct);

        NotificationService::starterKitAddAccountDelete($acct->profile_id, $starterKit->profile_id, $starterKit->id);

        app(StarterKitService::class)->getAccountStats($starterKit->profile_id, true);

        $acct->save();
        $starterKit->syncAccountCount();

        return $this->success();
    }

    /**
     * Remove the specified starter kit.
     */
    public function destroy(Request $request, $id)
    {
        $user = $request->user();
        $starterKit = StarterKit::findOrFail($id);

        if ($starterKit->profile_id !== $user->profile_id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        app(StarterKitService::class)->forget($starterKit->id);

        if ($starterKit->id && Storage::disk('s3')->exists('starterkit/'.$starterKit->id)) {
            Storage::disk('s3')->deleteDirectory('starterkit/'.$starterKit->id);
        }

        $starterKit->delete();

        app(StarterKitService::class)->flushStats();
        app(StarterKitService::class)->getAccountStats($user->profile_id, true);

        return response()->json([
            'message' => 'Starter kit deleted successfully',
        ]);
    }

    /**
     * Add an account to a starter kit.
     */
    public function addAccount(Request $request, $id)
    {
        $user = $request->user();
        $starterKit = StarterKit::findOrFail($id);

        if ($starterKit->profile_id !== $user->profile_id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'profile_id' => 'required|exists:profiles,id',
        ]);

        $config = app(StarterKitService::class)->getConfig();
        $adminApproval = data_get($config, 'requires_admin_approval', true);

        $targetProfile = Profile::where('can_use_starter_kits', true)->findOrFail($validated['profile_id']);

        $existing = StarterKitAccount::withTrashed()->where('starter_kit_id', $starterKit->id)
            ->where('profile_id', $targetProfile->id)
            ->first();

        if ($existing) {
            if ($existing->kit_status === 1 && $existing->rejected_at == null && $existing->approved_at !== null) {
                $existing->forceDelete();
            } elseif ($existing->kit_status === 0 && $existing->rejected_at == null && $existing->approved_at == null) {
                $existing->forceDelete();
            } elseif ($existing->rejected_at || $existing->kit_status == 2) {
                return response()->json([
                    'error' => 'Account has previously rejected your request',
                ], 422);
            } else {
                return response()->json([
                    'error' => 'Account already in starter kit',
                ], 422);
            }
        }

        if (! $starterKit->canAddAccount($user->profile, $targetProfile)) {
            return response()->json([
                'error' => 'You do not have permission to add this account',
            ], 403);
        }

        if ($user->profile_id == $targetProfile->id) {
            $requiresApproval = false;
        } else {
            $requiresApproval = $targetProfile->starterKitRequiresApproval();
        }

        $approval = $requiresApproval ? StarterKitAccount::STATUS_PENDING : StarterKitAccount::STATUS_APPROVED;

        if ($adminApproval && $approval && $starterKit->status != 10) {
            $approval = StarterKitAccount::STATUS_APPROVED_PENDING_ADMIN_REVIEW;
        }

        $nextOrder = StarterKitAccount::where('starter_kit_id', $starterKit->id)->max('order') + 1;

        $account = StarterKitAccount::create([
            'starter_kit_id' => $starterKit->id,
            'profile_id' => $targetProfile->id,
            'kit_status' => $approval,
            'order' => $nextOrder,
            'approved_at' => $approval === 1 ? now() : null,
        ]);

        if (! $adminApproval || $starterKit->status == 10) {
            NotificationService::starterKitAddAccount($targetProfile->id, $user->profile_id, $starterKit->id);
        }

        $starterKit->update([
            'total_accounts' => $starterKit->starterKitAccounts()->count(),
            'approved_accounts' => $starterKit->starterKitAccounts()->approved()->count(),
        ]);

        app(StarterKitService::class)->flushStats();
        app(StarterKitService::class)->forget($starterKit->id);

        return response()->json([
            'message' => $requiresApproval ? 'Account added, pending approval' : 'Account added successfully',
            'account' => $account->toPublicArray(),
            'requires_approval' => $requiresApproval,
        ]);
    }

    /**
     * Remove an account from a starter kit.
     */
    public function removeAccount(Request $request, $id, $accountId)
    {
        $user = $request->user();
        $starterKit = StarterKit::findOrFail($id);

        if ($starterKit->profile_id !== $user->profile_id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $account = StarterKitAccount::withTrashed()
            ->where('starter_kit_id', $starterKit->id)
            ->where('profile_id', $accountId)
            ->firstOrFail();

        NotificationService::starterKitAddAccountDelete($account->profile_id, $starterKit->profile_id, $starterKit->id);

        if ($account->kit_status === StarterKitAccount::STATUS_APPROVED) {
            NotificationService::starterKitYourAccountRemoved($account->profile_id, $starterKit->profile_id, $starterKit->id);
        }

        if ($account->kit_status === StarterKitAccount::STATUS_APPROVED_PENDING_ADMIN_REVIEW && $account->rejected_at === null) {
            $account->forceDelete();
        } else {
            $account->delete();
        }

        app(StarterKitService::class)->forget($starterKit->id);

        $starterKit->update([
            'total_accounts' => $starterKit->starterKitAccounts()->count(),
            'approved_accounts' => $starterKit->starterKitAccounts()->approved()->count(),
        ]);

        return response()->json([
            'message' => 'Account removed successfully',
        ]);
    }

    /**
     * Use/apply a starter kit (follow all accounts).
     */
    public function use(Request $request, $id)
    {
        $user = $request->user();
        $profile = $user->profile;
        $pid = $profile->id;

        if (! $user->can_use_starter_kits) {
            return $this->error('You have been restricted from using Starter Kits. Please contact support for assistance.');
        }

        $starterKit = StarterKit::active()->findOrFail($id);

        if ($starterKit->visibility === StarterKit::VISIBILITY_PRIVATE
            && $starterKit->profile_id !== $profile->id) {
            return response()->json(['error' => 'Not found'], 404);
        }

        $followedCount = 0;
        $alreadyFollowingCount = 0;
        $followedProfileIds = [];

        foreach ($starterKit->approvedAccounts as $account) {
            if ($account->id == $profile->id) {
                continue;
            }

            if ($account->manuallyApprovesFollowers) {
                continue;
            }

            if (Follower::whereProfileId($pid)->whereFollowingId($account->id)->exists()) {
                $alreadyFollowingCount++;

                continue;
            }

            if ($account->local) {
                $res = Follower::updateOrCreate([
                    'profile_id' => $pid,
                    'following_id' => $account->id,
                ], [
                    'following_is_local' => (bool) $profile->local,
                ]);

                if ($res->wasRecentlyCreated) {
                    NotificationService::newFollowerFromStarterKit($account->id, $pid, $starterKit);
                }
            } else {
                FollowRequest::whereProfileId($pid)->whereFollowingId($account->id)->delete();

                $res = FollowRequest::firstOrCreate([
                    'profile_id' => $pid,
                    'following_id' => $account->id,
                    'profile_is_local' => true,
                    'following_is_local' => false,
                    'following_state' => 0,
                ]);

                DeliverFollowRequest::dispatch($res)->onQueue('activitypub-out');
            }
            $followedProfileIds[] = $account->id;
            $followedCount++;
        }

        StarterKitUse::create([
            'profile_id' => $pid,
            'starter_kit_id' => $starterKit->id,
            'followed_profile_ids' => $followedProfileIds,
        ]);

        $starterKit->incrementUses();

        app(StarterKitService::class)->flushStats();

        return response()->json([
            'message' => 'Starter kit applied successfully',
            'followed_count' => $followedCount,
            'already_following_count' => $alreadyFollowingCount,
            'total_accounts' => $starterKit->approvedAccounts->count(),
        ]);
    }

    public function reuse(Request $request, $id)
    {
        $user = $request->user();
        $profile = $user->profile;
        $pid = $profile->id;

        if (! $user->can_use_starter_kits) {
            return $this->error('You have been restricted from using Starter Kits. Please contact support for assistance.');
        }

        $starterKit = StarterKit::active()->findOrFail($id);

        if ($starterKit->visibility === StarterKit::VISIBILITY_PRIVATE
            && $starterKit->profile_id !== $profile->id) {
            return response()->json(['error' => 'Not found'], 404);
        }

        $use = StarterKitUse::whereStarterKitId($starterKit->id)
            ->whereProfileId($pid)
            ->first();

        if (! $use) {
            return $this->error('You have not used this kit yet.');
        }

        $alreadyFollowed = $use->followed_profile_ids ?? [];
        $followedCount = 0;
        $alreadyFollowingCount = 0;
        $newlyFollowedProfileIds = [];

        foreach ($starterKit->approvedAccounts as $account) {
            if ($account->id == $profile->id) {
                continue;
            }

            if ($account->manuallyApprovesFollowers) {
                continue;
            }

            if (in_array($account->id, $alreadyFollowed)) {
                $alreadyFollowingCount++;

                continue;
            }

            if (Follower::whereProfileId($pid)->whereFollowingId($account->id)->exists()) {
                $alreadyFollowingCount++;

                continue;
            }

            if ($account->local) {
                $res = Follower::updateOrCreate([
                    'profile_id' => $pid,
                    'following_id' => $account->id,
                ], [
                    'following_is_local' => (bool) $profile->local,
                ]);

                if ($res->wasRecentlyCreated) {
                    NotificationService::newFollowerFromStarterKit($account->id, $pid, $starterKit);
                }
            } else {
                FollowRequest::whereProfileId($pid)->whereFollowingId($account->id)->delete();

                $res = FollowRequest::firstOrCreate([
                    'profile_id' => $pid,
                    'following_id' => $account->id,
                    'profile_is_local' => true,
                    'following_is_local' => false,
                    'following_state' => 0,
                ]);

                DeliverFollowRequest::dispatch($res)->onQueue('activitypub-out');
            }

            $newlyFollowedProfileIds[] = $account->id;
            $followedCount++;
        }

        if ($followedCount === 0) {
            return $this->error('No new accounts to follow.');
        }

        $use->followed_profile_ids = array_values(array_unique(
            array_merge($alreadyFollowed, $newlyFollowedProfileIds)
        ));
        $use->save();

        app(StarterKitService::class)->flushStats();

        return response()->json([
            'message' => 'Starter kit re-applied successfully',
            'followed_count' => $followedCount,
            'already_following_count' => $alreadyFollowingCount,
            'total_accounts' => $starterKit->approvedAccounts->count(),
        ]);
    }

    public function uploadIcon(Request $request, $id)
    {
        $user = $request->user();
        $starterKit = StarterKit::whereProfileId($user->profile_id)->findOrFail($id);

        $request->validate([
            'icon' => 'required|image|mimes:jpeg,png,jpg,webp|max:2048|dimensions:ratio=1',
        ]);

        if ($user->is_admin) {
            if ($starterKit->icon_path) {
                Storage::disk('s3')->delete($starterKit->icon_path);
            }

            $encoded = Image::read($request->file('icon'))
                ->cover(400, 400)
                ->toWebp(quality: 85);

            $path = 'starterkit/'.$starterKit->id.'/icon-'.Str::random(12).'.webp';
            Storage::disk('s3')->put($path, $encoded, 'public');

            $url = Storage::disk('s3')->url($path);
            $starterKit->update(['icon_path' => $path, 'icon_url' => $url]);

            app(StarterKitService::class)->forget($starterKit->id);

            return response()->json([
                'icon_url' => $url,
            ]);
        } else {
            if (app(StarterKitPendingChangeService::class)->hasPendingField($starterKit, 'icon_path')) {
                return response()->json([
                    'error' => trans('common.anIconUpdateIsAlreadyAwaitingApproval').'. '.trans('common.pleaseWaitForItToBeReviewedBeforeUploadingANewOne').'.',
                ], 422);
            }

            $encoded = Image::read($request->file('icon'))
                ->cover(400, 400)
                ->toWebp(quality: 85);

            $path = 'starterkit/'.$starterKit->id.'/pending-icon-'.Str::random(32).'.webp';
            Storage::disk('s3')->put($path, $encoded, 'private');
            $url = Storage::disk('s3')->url($path);

            $pmt = app(PrivateMediaTokenService::class)->create(
                profileId: $starterKit->profile_id,
                path: $path,
                ttlHours: 720
            );

            $url = route('media.private.show', $pmt->id);

            $changes = [
                'icon_path' => [
                    'value' => $path,
                    'preview_url' => $url,
                ],
            ];

            $skpc = app(StarterKitPendingChangeService::class)->submit($starterKit, $user->profile_id, $changes);

            app(PrivateMediaTokenService::class)->attach($pmt, $skpc);
            app(AdminDashboardService::class)->getReportsCount(true);

            return response()->json([
                'icon_url' => $url,
            ]);
        }
    }

    public function deleteIcon(Request $request, $id)
    {
        $user = $request->user();
        $starterKit = StarterKit::whereProfileId($user->profile_id)->findOrFail($id);

        if ($user->is_admin) {
            if ($starterKit->icon_path && Storage::disk('s3')->exists($starterKit->icon_path)) {
                Storage::disk('s3')->delete($starterKit->icon_path);
            }
            $starterKit->update(['icon_path' => null, 'icon_url' => null]);
            app(StarterKitService::class)->forget($starterKit->id);
        } else {
            app(StarterKitPendingChangeService::class)->deleteMedia($starterKit, $user->profile_id, 'icon_path');
            app(AdminDashboardService::class)->getReportsCount(true);
        }

        return response()->json(['ok' => true]);
    }

    public function uploadHeader(Request $request, $id)
    {
        $user = $request->user();
        $starterKit = StarterKit::whereProfileId($user->profile_id)->findOrFail($id);

        $request->validate([
            'header' => 'required|image|mimes:jpeg,png,jpg,webp|max:5120',
        ]);

        if ($user->is_admin) {
            if ($starterKit->header_path) {
                Storage::disk('s3')->delete($starterKit->header_path);
            }

            $encoded = Image::read($request->file('header'))
                ->cover(1500, 600)
                ->toWebp(quality: 85);

            $path = 'starterkit/'.$starterKit->id.'/header-'.Str::random(12).'.webp';
            Storage::disk('s3')->put($path, $encoded, 'public');

            $url = Storage::disk('s3')->url($path);
            $starterKit->update(['header_path' => $path, 'header_url' => $url]);

            app(StarterKitService::class)->forget($starterKit->id);

            return response()->json([
                'header_url' => $url,
            ]);
        } else {
            if (app(StarterKitPendingChangeService::class)->hasPendingField($starterKit, 'header_path')) {
                return response()->json([
                    'error' => trans('common.aHeaderUpdateIsAlreadyAwaitingApproval').'. '.trans('common.pleaseWaitForItToBeReviewedBeforeUploadingANewOne').'.',
                ], 422);
            }

            $encoded = Image::read($request->file('header'))
                ->cover(1500, 600)
                ->toWebp(quality: 85);

            $path = 'starterkit/'.$starterKit->id.'/pending-header-'.Str::random(32).'.webp';
            Storage::disk('s3')->put($path, $encoded, 'private');
            $url = Storage::disk('s3')->url($path);

            $pmt = app(PrivateMediaTokenService::class)->create(
                profileId: $starterKit->profile_id,
                path: $path,
                ttlHours: 720
            );

            $url = route('media.private.show', $pmt->id);

            $changes = [
                'header_path' => [
                    'value' => $path,
                    'preview_url' => $url,
                ],
            ];

            $skpc = app(StarterKitPendingChangeService::class)->submit($starterKit, $user->profile_id, $changes);

            app(PrivateMediaTokenService::class)->attach($pmt, $skpc);
            app(AdminDashboardService::class)->getReportsCount(true);

            return response()->json([
                'header_url' => $url,
            ]);
        }
    }

    public function deleteHeader(Request $request, $id)
    {
        $user = $request->user();
        $starterKit = StarterKit::whereProfileId($user->profile_id)->findOrFail($id);

        if ($user->is_admin) {
            if ($starterKit->header_path && Storage::disk('s3')->exists($starterKit->header_path)) {
                Storage::disk('s3')->delete($starterKit->header_path);
            }
            $starterKit->update(['header_path' => null, 'header_url' => null]);
            // app(StarterKitService::class)->forget($starterKit->id);  # Dupe of line 1078?
        } else {
            app(StarterKitPendingChangeService::class)->deleteMedia($starterKit, $user->profile_id, 'header_path');
            app(AdminDashboardService::class)->getReportsCount(true);
        }

        app(StarterKitService::class)->forget($starterKit->id);

        return response()->json(['ok' => true]);
    }

    public function syncHashtags(StarterKit $starterKit, array $tagNames): void
    {
        $tagNames = collect($tagNames)
            ->map(fn ($tag) => ltrim($tag, '#'))
            ->filter()
            ->unique()
            ->values();

        $hashtagIds = $tagNames->map(function ($name, $index) {
            $hashtag = Hashtag::firstOrCreate(
                ['name_normalized' => strtolower($name), 'name' => $name],
                ['can_autolink' => true]
            );

            return ['id' => $hashtag->id, 'order' => $index];
        });

        $incomingIds = $hashtagIds->pluck('id')->all();

        StarterKitTag::where('starter_kit_id', $starterKit->id)
            ->whereNotIn('hashtag_id', $incomingIds)
            ->delete();

        $existingIds = StarterKitTag::where('starter_kit_id', $starterKit->id)
            ->pluck('hashtag_id')
            ->all();

        foreach ($hashtagIds as $item) {
            if (in_array($item['id'], $existingIds)) {
                StarterKitTag::where('starter_kit_id', $starterKit->id)
                    ->where('hashtag_id', $item['id'])
                    ->update(['order' => $item['order']]);
            } else {
                StarterKitTag::create([
                    'starter_kit_id' => $starterKit->id,
                    'hashtag_id' => $item['id'],
                    'status' => StarterKitTag::STATUS_APPROVED,
                    'order' => $item['order'],
                ]);
            }
        }
    }
}
