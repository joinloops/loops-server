<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Rules\HCaptchaRule;
use App\Rules\TurnstileRule;
use App\Services\AccountService;
use App\Services\CaptchaService;
use Illuminate\Foundation\Auth\AuthenticatesUsers;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;

class LoginController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Login Controller
    |--------------------------------------------------------------------------
    |
    | This controller handles authenticating users for the application and
    | redirecting them to your home screen. The controller uses a trait
    | to conveniently provide its functionality to your applications.
    |
    */

    use AuthenticatesUsers;

    /**
     * Where to redirect users after login.
     *
     * @var string
     */
    protected $redirectTo = '/';

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('guest')->except('logout');
        $this->middleware('auth')->only('logout');
    }

    /**
     * Log the user out of the application.
     *
     * @return \Illuminate\Http\RedirectResponse
     */
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

        $this->guard()->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        if ($isOAuthRedirect) {
            return redirect($oauthAuthorizeUrl);
        }

        return $request->wantsJson()
            ? new JsonResponse([], 204)
            : redirect('/');
    }

    /**
     * Validate the user login request.
     *
     * @return void
     *
     * @throws \Illuminate\Validation\ValidationException
     */
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
                $rules['captcha_token'] = [
                    'required',
                    'string',
                    new TurnstileRule(new CaptchaService),
                ];
            } elseif ($driver === 'hcaptcha') {
                $rules['captcha_token'] = [
                    'required',
                    'string',
                    new HCaptchaRule(new CaptchaService),
                ];
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
            // Automatically send verification code before logging out
            $existing = \App\Models\UserRegisterVerify::whereEmail($user->email)
                ->whereNull('verified_at')
                ->latest()
                ->first();

            if (! $existing) {
                $sessionKey = \Illuminate\Support\Str::random(32);
                $reg = new \App\Models\UserRegisterVerify;
                $reg->session_key = $sessionKey;
                $reg->email = $user->email;
                $reg->verify_code = (string) app(\App\Support\VerificationCode::class)->generate();
                $reg->ip_address = $request->ip();
                $reg->user_agent = $request->userAgent();
                $reg->email_last_sent_at = now();
                $reg->save();

                $request->session()->put('user:reg:session_key', $sessionKey);
                $request->session()->put('user:reg:email', $reg->email);
                $request->session()->put('user:reg:id', $reg->id);

                \App\Jobs\Auth\NewAccountEmailVerifyJob::dispatch($reg);
            } else {
                $request->session()->put('user:reg:session_key', $existing->session_key);
                $request->session()->put('user:reg:email', $existing->email);
                $request->session()->put('user:reg:id', $existing->id);
            }

            $this->guard()->logout();

            $request->session()->regenerate();

            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'redirect' => url('/auth/verify-email'),
                    'message' => 'You need to verify your email.',
                    'email' => $user->email,
                    'verification_sent' => true,
                ], 403);
            }

            return redirect()->route('login')->withErrors([
                $this->username() => 'You need to verify your email.',
            ]);
        }

        if ((int) $user->status === 6) {
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

            $this->guard()->logout();

            return response()->json(['has_2fa' => true]);
        }

        return $this->authenticated($request, $user)
                ?: redirect()->intended($this->redirectPath());
    }

    /**
     * The user has been authenticated.
     * Override to handle OAuth authorization flow.
     *
     * @param  mixed  $user
     * @return mixed
     */
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
            });
            AccountService::del($user->profile_id);
            AccountService::get($user->profile_id);
        }

        if ($intendedUrl && str_contains($intendedUrl, '/oauth/authorize')) {
            session()->forget('url.intended');

            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'redirect' => $intendedUrl,
                ]);
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
