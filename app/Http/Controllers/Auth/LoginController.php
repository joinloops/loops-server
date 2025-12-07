<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Rules\HCaptchaRule;
use App\Rules\TurnstileRule;
use App\Services\CaptchaService;
use Illuminate\Foundation\Auth\AuthenticatesUsers;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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
            $rules['captcha_type'] = ['required', 'in:turnstile,hcaptcha'];

            if (config('captcha.driver') === 'turnstile') {
                $rules['captcha_token'] = [
                    'required',
                    'string',
                    new TurnstileRule(new CaptchaService),
                ];
            } elseif (config('captcha.driver') === 'hcaptcha') {
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

        if ((int) $user->status === 6) {
            $this->guard()->logout();

            $request->session()->invalidate();
            $request->session()->regenerateToken();

            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'message' => 'Your account has been disabled.',
                ], 403);
            }

            return redirect()->route('login')->withErrors([
                $this->username() => 'Your account has been disabled.',
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
            $user->update(['status' => 1, 'delete_after' => null]);
            $user->profile->update(['status' => 1]);
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
