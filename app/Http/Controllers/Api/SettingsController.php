<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Traits\ApiHelpers;
use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateAccountPassword;
use App\Http\Requests\UpdateAvatarRequest;
use App\Http\Requests\UpdateProfileRequest;
use App\Http\Resources\BlockedAccountResource;
use App\Http\Resources\ProfileResource;
use App\Models\Profile;
use App\Models\UserFilter;
use App\Services\AccountService;
use App\Services\AvatarService;
use App\Services\TwoFactorService;
use App\Services\UserAuditLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use PragmaRX\Google2FA\Google2FA;

class SettingsController extends Controller
{
    use ApiHelpers;

    protected UserAuditLogService $auditService;

    public function __construct(UserAuditLogService $auditService)
    {
        $this->middleware('auth');
        $this->auditService = $auditService;
    }

    public function storeBio(UpdateProfileRequest $request)
    {
        $user = $request->user();
        $pid = $user->profile_id;
        $profile = $user->profile;
        $originalData = $user->only(['name', 'bio']);

        if ($request->filled('name')) {
            $name = $request->input('name') ?: AccountService::getDefaultDisplayName($user->profile_id);
            $profile->name = $this->purify($name);
            $user->name = $this->purify($name);
            $user->save();
        }

        if ($request->filled('bio')) {
            $profile->bio = $this->purify($request->input('bio'));
        }

        $profile->save();

        $changedFields = [];
        foreach ($request->validated() as $key => $value) {
            if ($originalData[$key] !== $value) {
                $changedFields[] = $key;
            }
        }

        $this->auditService->logProfileUpdated($user, $changedFields);

        AccountService::del($pid);

        return $this->data(AccountService::get($pid));
    }

    public function updateAvatar(UpdateAvatarRequest $request)
    {
        $user = $request->user();
        $profile = $user->profile;

        AvatarService::updateAvatar($profile, $request->file('avatar'));
        $this->auditService->logProfileAvatarUpdated($user);

        return $this->data(AccountService::get($profile->id));
    }

    public function deleteAvatar(Request $request)
    {
        $user = $request->user();
        $profile = $user->profile;

        AvatarService::deleteAvatar($profile);
        $this->auditService->logProfileAvatarDeleted($user);

        return $this->data(AccountService::get($profile->id));
    }

    public function securityConfig(Request $request)
    {
        return $this->data(['two_factor_enabled' => (bool) $request->user()->has_2fa]);
    }

    public function updatePassword(UpdateAccountPassword $request)
    {
        $newPassword = $request->input('password');
        $user = $request->user();
        $user->password = Hash::make($newPassword);
        $user->save();
        $this->auditService->logPasswordChanged($user);

        return $this->success();
    }

    public function disableTwoFactor(Request $request)
    {
        $user = $request->user();
        if (! $user->has_2fa) {
            return $this->error('2FA already setup');
        }

        $user->two_factor_secret = null;
        $user->two_factor_backups = null;
        $user->has_2fa = false;
        $user->save();

        $this->auditService->logTwoFactorDisabled($user);

        return $this->success();
    }

    public function setupTwoFactor(Request $request)
    {
        $user = $request->user();
        if ($user->has_2fa) {
            return $this->error('2FA already setup');
        }
        $generate = TwoFactorService::generate($user->id, $user->email);
        $request->session()->put('2fa_secret', $generate['key']);

        return $this->data(['qr' => $generate['qr']]);
    }

    public function confirmTwoFactor(Request $request)
    {
        $request->validate([
            'code' => 'required|int|min:111111|max:999999',
        ]);

        $user = $request->user();
        if ($user->has_2fa) {
            return $this->error('2FA already setup');
        }

        $google2fa = new Google2FA;
        $secret = $request->input('code');
        $valid = $google2fa->verifyKey($request->session()->get('2fa_secret'), $secret);

        if (! $valid) {
            return $this->error('Invalid code');
        }

        $user->two_factor_secret = $request->session()->get('2fa_secret');
        $user->has_2fa = true;
        $user->save();

        $this->auditService->logTwoFactorEnabled($user);

        return $this->success();
    }

    public function blockedAccounts(Request $request)
    {
        $request->validate([
            'q' => [
                'sometimes',
                'string',
                'min:1',
                'max:50',
                'regex:/^[a-zA-Z0-9_\.@]+$/',
            ],
        ]);

        $search = $request->input('q');

        $res = UserFilter::whereProfileId($request->user()->profile_id)
            ->when($search, function ($query, $search) {
                $query->join('profiles', 'user_filters.account_id', '=', 'profiles.id')
                    ->where('profiles.username', 'like', $search.'%')
                    ->select('user_filters.*');
            })->orderByDesc('id')->cursorPaginate(10);

        return BlockedAccountResource::collection($res);
    }

    public function totalBlockedAccounts(Request $request)
    {
        return $this->data(['count' => UserFilter::whereProfileId($request->user()->profile_id)->count()]);
    }

    public function blockedAccountSearch(Request $request)
    {
        $request->validate([
            'q' => [
                'required',
                'string',
                'min:1',
                'max:30',
                'regex:/^[a-zA-Z0-9_\.@]+$/',
            ],
        ], [
            'q.regex' => 'Search query can only contain letters, numbers, underscores, and dots.',
            'q.min' => 'Please enter at least 1 character to search.',
            'q.max' => 'Search query is too long.',
        ]);

        $currentProfileId = $request->user()->profile_id;
        $searchQuery = trim($request->input('q'));

        $searchQuery = str_replace(['%', '_'], ['\%', '\_'], $searchQuery);

        try {
            $profiles = Profile::query()
                ->where('username', 'like', $searchQuery.'%')
                ->whereNot('id', $currentProfileId)
                ->where('is_hidden', false)
                ->where('status', 1)

                ->whereNotExists(function ($query) use ($currentProfileId) {
                    $query->select('id')
                        ->from('user_filters')
                        ->whereColumn('user_filters.account_id', 'profiles.id')
                        ->where('user_filters.profile_id', $currentProfileId);
                })
                ->select(['id', 'username', 'name', 'avatar', 'bio', 'followers', 'following', 'video_count', 'links', 'created_at'])
                ->limit(10)
                ->orderBy('username', 'asc')
                ->get();

            return ProfileResource::collection($profiles);
        } catch (\Exception $e) {
            return $this->error('Search temporarily unavailable. Please try again.');
        }
    }
}
