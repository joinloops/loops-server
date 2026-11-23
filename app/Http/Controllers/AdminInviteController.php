<?php

namespace App\Http\Controllers;

use App\Http\Requests\InviteVerifyBirthdateRequest;
use App\Http\Requests\InviteVerifyRequest;
use App\Http\Requests\InviteVerifyUsernameRequest;
use App\Mail\AdminInviteJoined;
use App\Models\AdminInvite;
use App\Models\Follower;
use App\Models\User;
use App\Rules\NotBlockedEmail;
use App\Rules\NotBlockedIp;
use App\Rules\NotBlockedUsername;
use App\Rules\ValidUsername;
use App\Services\AccessRuleService;
use App\Services\AccountService;
use App\Services\NotificationService;
use App\Services\UsernameService;
use Carbon\Carbon;
use Dedoc\Scramble\Attributes\ExcludeAllRoutesFromDocs;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

#[ExcludeAllRoutesFromDocs]
class AdminInviteController extends Controller
{
    const INVITE_SESSION_KEY = 'loops_admn_invite_session:';

    public function __construct() {}

    public function verifyInvite(InviteVerifyRequest $request)
    {
        sleep(random_int(2, 5));

        $validated = $request->validated();

        $key = 'loops_invite_verify:'.$request->ip();
        $attempts = Cache::get($key, 0);

        if ($attempts >= 5) {
            return response()->json([
                'error' => 'Too many attempts. Please try again later.',
            ], 429);
        }

        Cache::put($key, $attempts + 1, now()->addMinutes(15));

        $token = $validated['token'];

        $invites = AdminInvite::where('is_active', true)
            ->where(function ($query) {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->where(function ($query) {
                $query->where('max_uses', 0)
                    ->orWhereRaw('total_uses < max_uses');
            })
            ->get();

        $validInvite = null;

        foreach ($invites as $invite) {
            if (hash_equals($invite->invite_key, $token)) {
                $validInvite = $invite;
            }
        }

        if (! $validInvite) {
            usleep(random_int(10000, 50000));

            return response()->json([
                'error' => 'Invalid or expired invite token.',
            ], 404);
        }

        Cache::forget($key);

        $sessionToken = Str::random(64);
        $sessionKey = self::INVITE_SESSION_KEY.$sessionToken;

        Cache::put($sessionKey, [
            'invite_id' => $validInvite->id,
            'ip_address' => $request->ip(),
            'invite_key' => $validInvite->invite_key,
            'created_at' => now()->timestamp,
        ], now()->addMinutes(15));

        $adminAccount = AccountService::compact($validInvite->invited_by);

        return response()->json([
            'valid' => true,
            'message' => $validInvite->message,
            'invited_by' => $adminAccount,
            'session_token' => $sessionToken,
        ]);
    }

    public function verifyAge(InviteVerifyBirthdateRequest $request)
    {
        $validated = $request->validated();

        if (! $this->verifySessionToken($validated['session_token'])) {
            return response()->json([
                'message' => 'Invalid or expired session. Please start over.',
            ], 401);
        }

        $minAge = config('loops.registration.min_years_old', 16);
        $age = Carbon::parse($validated['birthdate'])->diffInYears(now());
        $allowed = $age >= $minAge;

        return response()->json([
            'allowed' => $allowed,
            'minAge' => $minAge,
            'message' => $allowed
                ? __('common.birthdateVerified')
                : __('common.youMustBeAtLeastXYearsOld', ['years' => $minAge]),
        ]);
    }

    public function checkUsername(InviteVerifyUsernameRequest $request)
    {
        $validated = $request->validated();

        $token = $validated['session_token'];
        if (! $this->verifySessionToken($token)) {
            return response()->json([
                'message' => 'Invalid or expired session. Please start over.',
            ], 401);
        }

        $key = 'loops_username_verify:'.$token;
        $attempts = Cache::get($key, 0);

        if ($attempts >= 20) {
            return response()->json([
                'error' => 'Too many attempts. Please try again later.',
            ], 429);
        }

        Cache::put($key, $attempts + 1, now()->addMinutes(15));

        $username = $validated['username'];

        $exists = User::where('username', $username)->exists();
        $blocked = app(AccessRuleService::class)->isUsernameBlocked($username);

        return response()->json([
            'valid' => ! $exists && ! $blocked,
        ]);
    }

    public function register(Request $request)
    {
        $validated = $request->validate([
            'invite_key' => 'required|string|min:40|max:46',
            'name' => ['required', 'string', 'min:1', 'max:255'],
            'birthdate' => 'required|date|before:today',
            'username' => [
                'required',
                'min:2',
                'max:24',
                'unique:users,username',
                new ValidUsername,
                function ($attribute, $value, $fail) {
                    if (app(UsernameService::class)->isReserved($value)) {
                        $fail('This username is reserved.');
                    }
                },
                new NotBlockedUsername,
            ],
            'session_token' => 'required|string|min:64|max:64',
            'email' => ['required', new NotBlockedIp, 'string', 'email:rfc,dns,spoof,strict', new NotBlockedEmail, 'max:255', 'unique:users'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $token = $validated['session_token'];

        if (! $this->verifySessionToken($token)) {
            return response()->json([
                'message' => 'Invalid or expired session. Please start over.',
            ], 401);
        }
        $session = $this->getSessionData($token);

        $invite = AdminInvite::where('is_active', true)
            ->where(function ($query) {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->where(function ($query) {
                $query->where('max_uses', 0)
                    ->orWhereRaw('total_uses < max_uses');
            })
            ->whereKey($session['invite_id'])
            ->first();

        if (! $invite) {
            return response()->json([
                'message' => 'Invalid or expired invite, it may have expired or reached the max use limit.',
            ], 401);
        }

        $name = $validated['name'];
        $email = $validated['email'];
        $username = $validated['username'];
        $password = $validated['password'];
        $birthDate = $validated['birthdate'];

        $user = new User;
        $user->name = $name;
        $user->username = $username;
        $user->email = $email;
        $user->password = Hash::make($password);
        $user->email_verified_at = $invite->verify_email ? null : now();
        $user->birth_date = $birthDate;
        $user->admin_invite_id = $invite->id;
        $user->register_ip = $request->ip();
        $user->last_ip = $request->ip();
        $user->save();

        $invite->increment('total_uses');

        if ($invite->autofollow_accounts) {
            sleep(2);

            foreach ($invite->autofollow_accounts as $id) {
                Follower::updateOrCreate([
                    'profile_id' => $user->profile_id,
                    'following_id' => $id,
                ], [
                    'following_is_local' => true,
                    'profile_is_local' => true,
                ]);

                NotificationService::newFollower($id, $user->profile_id);
            }
        }

        if ($invite->email_admin_on_join && config('mail.default') !== 'log') {
            $admin = User::where('is_admin', true)->first();
            if ($admin && $admin->email) {
                Mail::to($admin->email)->send(new AdminInviteJoined($user, $invite));
            }
        }

        return response()->json();
    }

    private function verifySessionToken($token)
    {
        $sessionKey = self::INVITE_SESSION_KEY.$token;

        return Cache::has($sessionKey);
    }

    private function getSessionData($token)
    {
        $sessionKey = self::INVITE_SESSION_KEY.$token;

        return Cache::get($sessionKey);
    }
}
