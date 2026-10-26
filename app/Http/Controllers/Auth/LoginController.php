<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Rules\HCaptchaRule;
use App\Rules\TurnstileRule;
use App\Services\AccountDeletionService;
use App\Services\AccountService;
use App\Services\AccountSwitcherService;
use App\Services\CaptchaService;
use App\Services\StarterKitService;
use Illuminate\Foundation\Auth\AuthenticatesUsers;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;

class LoginController extends Controller
{
    use AuthenticatesUsers;

    protected $redirectTo = '/';

    public function __construct(protected AccountSwitcherService $accountSwitcher)
    {
        $this->middleware('guest')->except(['logout', 'login']);
        $this->middleware('auth')->only('logout');

        $this->middleware(function (Request $request, $next) {
            // @phpstan-ignore-next-line
            if (! auth()->check()) {
                return $next($request);
            }

            if ($request->boolean('add_account')) {
                return $next($request);
            }

            return ($request->expectsJson() || $request->ajax())
                ? response()->json(['redirect' => url('/')])
                : redirect('/');
        })->only('login');
    }

    public function login(Request $request)
    {
        // @phpstan-ignore-next-line
        if (auth()->check() && $request->boolean('add_account')) {
            return $this->addAccount($request);
        }

        $this->validateLogin($request);

        // @phpstan-ignore-next-line
        if (method_exists($this, 'hasTooManyLoginAttempts') &&
            $this->hasTooManyLoginAttempts($request)) {
            $this->fireLockoutEvent($request);

            return $this->sendLockoutResponse($request);
        }

        if ($this->attemptLogin($request)) {
            if ($request->hasSession()) {
                $request->session()->put('auth.password_confirmed_at', time());
            }

            return $this->sendLoginResponse($request);
        }

        $this->incrementLoginAttempts($request);

        return $this->sendFailedLoginResponse($request);
    }

    protected function addAccount(Request $request)
    {
        $activeUser = $this->guard()->user();

        if ($this->accountSwitcher->isAtCapacity()) {
            return response()->json([
                'message' => 'You have reached the maximum number of linked accounts.',
            ], 422);
        }

        $this->validateLogin($request);

        // @phpstan-ignore-next-line
        if (method_exists($this, 'hasTooManyLoginAttempts') &&
            $this->hasTooManyLoginAttempts($request)) {
            $this->fireLockoutEvent($request);

            return $this->sendLockoutResponse($request);
        }

        if (! $this->guard()->validate($this->credentials($request))) {
            $this->incrementLoginAttempts($request);

            return $this->sendFailedLoginResponse($request);
        }

        $this->clearLoginAttempts($request);

        // @phpstan-ignore-next-line
        $newUser = $this->guard()->getLastAttempted();

        if (! $newUser) {
            return response()->json([
                'message' => 'Unable to add that account right now.',
            ], 422);
        }

        if ((int) $newUser->id === (int) $activeUser->id) {
            return response()->json([
                'message' => 'That account is already active.',
            ], 422);
        }

        if (! $newUser->email_verified_at) {
            return response()->json([
                'message' => 'That account needs to verify its email before it can be added.',
            ], 422);
        }

        if (! in_array($newUser->status, [1, 7, 8], true)) {
            return response()->json([
                'message' => 'That account has been disabled and cannot be added.',
            ], 422);
        }

        if (app(AccountDeletionService::class)->isLocked($newUser)) {
            return response()->json([
                'message' => 'That account has been deleted and cannot be added.',
            ], 422);
        }

        if (in_array($newUser->status, [7, 8])) {
            $hasRecovered = true;

            DB::transaction(function () use ($newUser) {
                $newUser->update(['status' => 1, 'delete_after' => null]);
                $newUser->profile->update(['status' => 1]);
                $newUser->videos()->whereIn('status', [7, 8])->update(['status' => 2]);
                $newUser->videos()->whereIn('status', [9])->update(['status' => 1]);
                $newUser->comments()->whereIn('status', ['account_pending_deletion', 'account_disabled'])->update(['status' => 'active']);
                $newUser->commentReplies()->whereIn('status', ['account_pending_deletion', 'account_disabled'])->update(['status' => 'active']);
                $newUser->actorNotifications()->whereIn('actor_state', [7, 8])->update(['actor_state' => 1]);
                $newUser->starterKits()->update(['status' => DB::raw('previous_status')]);
            });
            app(StarterKitService::class)->flushStatsAndPopular();
            AccountService::del($newUser->profile_id);
            AccountService::get($newUser->profile_id);
        }

        if ($newUser->has_2fa) {
            Session::put('2fa:user:id', $newUser->id);
            Session::put('2fa:user:attempts', 0);
            Session::put('2fa:user:remember', $request->boolean('remember'));

            return response()->json(['has_2fa' => true]);
        }

        if ($newUser->must_change_password) {
            return response()->json([
                'message' => 'That account must change its password before it can be added.',
            ], 422);
        }

        $newUser->update(['last_ip' => $request->ip()]);

        $this->guard()->login($newUser, $request->boolean('remember'));

        $request->session()->put(
            'password_hash_'.Auth::getDefaultDriver(),
            $newUser->getAuthPassword()
        );

        $this->accountSwitcher->linkCurrentUser(
            remember: $request->boolean('remember')
        );

        return response()->json([
            'success' => true,
            'redirect' => url('/'),
        ]);
    }

    public function logout(Request $request)
    {
        $isOAuthRedirect = $request->input('oauth_redirect') === 'true';

        if ($isOAuthRedirect) {
            $oauthParams = array_filter([
                'client_id' => $request->input('client_id'),
                'redirect_uri' => $request->input('redirect_uri'),
                'response_type' => $request->input('response_type'),
                'scope' => $request->input('scope'),
                'state' => $request->input('state'),
            ]);

            $oauthAuthorizeUrl = url('/oauth/authorize?'.http_build_query($oauthParams));
        }

        $this->accountSwitcher->logoutAll();

        if ($isOAuthRedirect) {
            return redirect($oauthAuthorizeUrl);
        }

        return $request->wantsJson()
            ? new JsonResponse([], 204)
            : redirect('/');
    }

    protected function validateLogin(Request $request)
    {
        $hasCaptcha = config('captcha.enabled');

        $rules = [
            $this->username() => 'required|string',
            'password' => 'required|string',
        ];

        if ($hasCaptcha) {
            $driver = config('captcha.driver');

            if (! in_array($driver, ['turnstile', 'hcaptcha'])) {
                throw new \RuntimeException('Captcha is enabled but driver is not properly configured.');
            }

            $rules['captcha_type'] = ['required', 'in:turnstile,hcaptcha'];

            if ($driver === 'turnstile') {
                $rules['captcha_token'] = ['required', 'string', new TurnstileRule(new CaptchaService)];
            } elseif ($driver === 'hcaptcha') {
                $rules['captcha_token'] = ['required', 'string', new HCaptchaRule(new CaptchaService)];
            }
        }

        $request->validate($rules);
    }

    protected function sendLoginResponse(Request $request)
    {
        $request->session()->regenerate();

        $this->clearLoginAttempts($request);

        $user = $this->guard()->user();

        $user->update(['last_ip' => $request->ip()]);

        if (! $user->email_verified_at) {
            $this->guard()->logout();

            $request->session()->invalidate();
            $request->session()->regenerateToken();

            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'redirect' => url('/auth/verify-email'),
                    'message' => 'You need to verify your email.',
                ], 403);
            }

            return redirect()->route('login')->withErrors([
                $this->username() => 'You need to verify your email.',
            ]);
        }

        if (app(AccountDeletionService::class)->isLocked($user)) {
            $this->guard()->logout();

            $request->session()->invalidate();
            $request->session()->regenerateToken();

            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'message' => 'This account has been deleted.',
                ], 403);
            }

            return redirect()->route('login')->withErrors([
                $this->username() => 'This account has been deleted.',
            ]);
        }

        if (! in_array($user->status, [1, 7, 8], true)) {
            $this->guard()->logout();

            $request->session()->invalidate();
            $request->session()->regenerateToken();

            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'message' => '<div class="text-center">Your account has been disabled.<br/><br/>Please <a href="/contact" class="font-bold underline">contact support</a> for further assistance.</div>',
                ], 403);
            }

            return redirect()->route('login')->withErrors([
                $this->username() => '<div class="text-center">Your account has been disabled.<br/><br/>Please <a href="/contact" class="font-bold underline">contact support</a> for further assistance.</div>',
            ]);
        }

        if ($user->has_2fa) {
            Session::put('2fa:user:id', $user->id);
            Session::put('2fa:user:attempts', 0);
            Session::put('2fa:user:remember', $request->boolean('remember'));

            $this->guard()->logout();

            return response()->json(['has_2fa' => true]);
        }

        if ($user->must_change_password) {
            if ($intendedUrl = session('url.intended')) {
                Session::put(ForcePasswordChangeController::SESSION_INTENDED_URL, $intendedUrl);
            }

            Session::put(ForcePasswordChangeController::SESSION_USER_ID, $user->id);
            Session::put(ForcePasswordChangeController::SESSION_VERIFIED_AT, now()->timestamp);
            Session::put(ForcePasswordChangeController::SESSION_ATTEMPTS, 0);
            Session::put(
                ForcePasswordChangeController::SESSION_REMEMBER,
                (bool) $request->boolean('remember')
            );

            $this->guard()->logout();

            return response()->json(['must_change_password' => true]);
        }

        $this->accountSwitcher->linkCurrentUser(
            remember: $request->boolean('remember')
        );

        return $this->authenticated($request, $user)
                ?: redirect()->intended($this->redirectPath());
    }

    protected function authenticated(Request $request, $user)
    {
        $intendedUrl = session('url.intended');
        $hasRecovered = false;

        if (in_array($user->status, [7, 8])) {
            $hasRecovered = true;

            DB::transaction(function () use ($user) {
                $user->update(['status' => 1, 'delete_after' => null]);
                $user->profile->update(['status' => 1]);
                $user->videos()->whereIn('status', [7, 8])->update(['status' => 2]);
                $user->videos()->whereIn('status', [9])->update(['status' => 1]);
                $user->comments()->whereIn('status', ['account_pending_deletion', 'account_disabled'])->update(['status' => 'active']);
                $user->commentReplies()->whereIn('status', ['account_pending_deletion', 'account_disabled'])->update(['status' => 'active']);
                $user->actorNotifications()->whereIn('actor_state', [7, 8])->update(['actor_state' => 1]);
                $user->starterKits()->update(['status' => DB::raw('previous_status')]);
            });
            app(StarterKitService::class)->flushStatsAndPopular();
            AccountService::del($user->profile_id);
            AccountService::get($user->profile_id);
        }

        if ($intendedUrl && str_contains($intendedUrl, '/oauth/authorize')) {
            session()->forget('url.intended');

            if ($request->expectsJson() || $request->ajax()) {
                return response()->json(['redirect' => $intendedUrl]);
            }

            return redirect($intendedUrl);
        }

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'redirect' => $hasRecovered ? url('/dashboard/account/status?restored=true') : url('/'),
            ]);
        }
    }
}
