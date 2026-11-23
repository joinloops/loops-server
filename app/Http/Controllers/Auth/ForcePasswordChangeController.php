<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\ForcePasswordChangeRequest;
use App\Models\User;
use App\Services\AccessRuleService;
use App\Services\AccountSwitcherService;
use Dedoc\Scramble\Attributes\ExcludeAllRoutesFromDocs;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Session;

#[ExcludeAllRoutesFromDocs]
class ForcePasswordChangeController extends Controller
{
    public const SESSION_USER_ID = 'lps:pwchange:user:id';

    public const SESSION_VERIFIED_AT = 'lps:pwchange:user:verified_at';

    public const SESSION_ATTEMPTS = 'lps:pwchange:user:attempts';

    public const SESSION_INTENDED_URL = 'lps:pwchange:url.intended';

    public const SESSION_REMEMBER = 'lps:pwchange:user:remember';

    protected const SESSION_TTL_SECONDS = 600;

    protected const MAX_ATTEMPTS = 5;

    public function __construct()
    {
        $this->middleware('guest')->except(['cancel']);
    }

    public function change(ForcePasswordChangeRequest $request): JsonResponse
    {
        $userId = Session::get(self::SESSION_USER_ID);
        $verifiedAt = Session::get(self::SESSION_VERIFIED_AT);

        if (! $userId || ! $verifiedAt) {
            return $this->expired($request, 'No pending password change. Please sign in again.');
        }

        if ((int) $verifiedAt + self::SESSION_TTL_SECONDS < now()->timestamp) {
            return $this->expired($request, 'Your session has expired. Please sign in again.');
        }

        $attempts = (int) Session::get(self::SESSION_ATTEMPTS, 0);
        if ($attempts >= self::MAX_ATTEMPTS) {
            return $this->expired($request, 'Too many attempts. Please sign in again.');
        }

        $user = User::find($userId);

        if (! $user) {
            return $this->expired($request, 'Account not found. Please sign in again.');
        }

        if ($denial = app(AccessRuleService::class)->denial($request->ip(), AccessRuleService::CONTEXT_LOGIN, $user->email)) {
            return $this->expired($request, $denial['message']);
        }

        if (! $user->email_verified_at) {
            return $this->expired($request, 'You need to verify your email.');
        }

        if ((int) $user->status === 6) {
            return $this->expired($request, 'Your account has been disabled.');
        }

        $newPassword = $request->validated()['password'];

        if (Hash::check($newPassword, $user->password)) {
            Session::put(self::SESSION_ATTEMPTS, $attempts + 1);

            return response()->json([
                'message' => 'Your new password must be different from your current password.',
                'errors' => [
                    'password' => ['Your new password must be different from your current password.'],
                ],
            ], 422);
        }

        DB::transaction(function () use ($user, $newPassword, $request) {
            $user->forceFill([
                'password' => Hash::make($newPassword),
                'must_change_password' => false,
                'remember_token' => null,
                'last_ip' => $request->ip(),
            ])->save();
        });

        $remember = (bool) Session::pull(self::SESSION_REMEMBER, false);
        $intendedUrl = Session::pull(self::SESSION_INTENDED_URL);
        Session::forget([
            self::SESSION_USER_ID,
            self::SESSION_VERIFIED_AT,
            self::SESSION_ATTEMPTS,
        ]);

        Auth::guard()->login($user, $remember);
        $request->session()->regenerate();

        app(AccountSwitcherService::class)->linkCurrentUser(
            remember: $remember
        );

        if ($intendedUrl && str_contains($intendedUrl, '/oauth/authorize')) {
            return response()->json([
                'success' => true,
                'redirect' => $intendedUrl,
            ]);
        }

        return response()->json([
            'success' => true,
            'redirect' => url('/'),
        ]);
    }

    public function cancel(Request $request): JsonResponse
    {
        Session::forget([
            self::SESSION_USER_ID,
            self::SESSION_VERIFIED_AT,
            self::SESSION_ATTEMPTS,
            self::SESSION_INTENDED_URL,
            self::SESSION_REMEMBER,
        ]);

        return response()->json(['message' => 'Cancelled'], 200);
    }

    protected function expired(Request $request, string $message): JsonResponse
    {
        Session::forget([
            self::SESSION_USER_ID,
            self::SESSION_VERIFIED_AT,
            self::SESSION_ATTEMPTS,
            self::SESSION_INTENDED_URL,
            self::SESSION_REMEMBER,
        ]);

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json([
            'message' => $message,
            'requires_relogin' => true,
        ], 401);
    }
}
