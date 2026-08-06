<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Auth\ForcePasswordChangeController;
use App\Models\User;
use App\Services\AccountSwitcherService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Laravel\Passport\Passport;
use PragmaRX\Google2FA\Google2FA;

class AuthController extends Controller
{
    public function verifyTwoFactor(Request $request)
    {
        $request->validate(['otp_code' => 'required|integer|digits:6']);

        $user = User::find(session('2fa:user:id'));

        if (! $user) {
            return response()->json(['success' => false, 'error' => 'Invalid session.', 'force_relogin' => false]);
        }

        $attempts = (int) session('2fa:user:attempts', 0);

        if ($attempts >= 3) {
            session()->forget(['2fa:user:id', '2fa:user:attempts', '2fa:user:remember', 'url.intended']);

            return response()->json(['success' => false, 'error' => 'Too many attempts', 'force_relogin' => true]);
        }

        $request->session()->increment('2fa:user:attempts');

        $google2fa = new Google2FA;

        if (! $google2fa->verifyKey($user->two_factor_secret, $request->otp_code)) {
            return response()->json(['success' => false, 'error' => 'Invalid code, please try again.', 'force_relogin' => false]);
        }

        $remember = (bool) session('2fa:user:remember', false);

        Auth::login($user, $remember);

        $request->session()->put(
            'password_hash_'.Auth::getDefaultDriver(),
            $user->getAuthPassword()
        );

        app(AccountSwitcherService::class)->linkCurrentUser(remember: $remember);

        session()->forget(['2fa:user:id', '2fa:user:attempts', '2fa:user:remember']);

        $intendedUrl = session('url.intended');

        if ($user->must_change_password) {
            Session::put(ForcePasswordChangeController::SESSION_USER_ID, $user->id);
            Session::put(ForcePasswordChangeController::SESSION_VERIFIED_AT, now()->timestamp);
            Session::put(ForcePasswordChangeController::SESSION_ATTEMPTS, 0);

            if ($intendedUrl) {
                Session::put(ForcePasswordChangeController::SESSION_INTENDED_URL, $intendedUrl);
            }

            Auth::logout();

            return response()->json(['must_change_password' => true]);
        }

        if ($intendedUrl && str_contains($intendedUrl, '/oauth/authorize')) {
            session()->forget('url.intended');

            return response()->json(['success' => true, 'error' => null, 'redirect' => $intendedUrl]);
        }

        return response()->json(['success' => true, 'error' => null, 'redirect' => url('/')]);
    }

    public function registerApp(Request $request)
    {
        $data = $request->validate([
            'client_name' => ['required', 'string'],
            'redirect_uris' => ['required', 'array', 'min:1'],
            'redirect_uris.*' => ['required', 'string'],
        ]);

        $uris = collect($data['redirect_uris'])
            ->map('urldecode')
            ->filter()
            ->values()
            ->all();

        $client = Passport::client()->forceFill([
            'name' => e($request->client_name),
            'secret' => Str::random(40),
            'grant_types' => ['authorization_code', 'refresh_token'],
            'redirect_uris' => $uris,
            'revoked' => false,
        ]);

        $client->save();

        return response()->json([
            'client_id' => (string) $client->id,
            'client_name' => $client->name,
            'client_secret' => $client->plainSecret ?? null,
            'redirect_uris' => $data['redirect_uris'],
            'vapid_key' => null,
        ]);
    }
}
