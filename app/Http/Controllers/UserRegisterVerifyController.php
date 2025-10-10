<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Api\Traits\ApiHelpers;
use App\Http\Requests\StoreRegisterUsernameRequest;
use App\Http\Requests\StoreUserRegisterVerifyRequest;
use App\Jobs\Auth\NewAccountEmailVerifyJob;
use App\Models\AdminSetting;
use App\Models\User;
use App\Models\UserRegisterVerify;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserRegisterVerifyController extends Controller
{
    use ApiHelpers;

    public function sendEmailVerification(StoreUserRegisterVerifyRequest $request)
    {
        $sessionKey = Str::random(32);
        $request->session()->put('user:reg:session_key', $sessionKey);
        $reg = new UserRegisterVerify;
        $reg->session_key = $sessionKey;
        $reg->email = $request->input('email');
        $reg->verify_code = (string) random_int(111111, 999999);
        $reg->ip_address = $request->ip();
        $reg->user_agent = $request->userAgent();
        $reg->email_last_sent_at = now();
        $reg->save();
        $request->session()->put('user:reg:email', $request->input('email'));
        $request->session()->put('user:reg:id', $reg->id);
        $request->session()->put('user:reg:verify_attempts', 0);

        NewAccountEmailVerifyJob::dispatch($reg);

        return $this->success();
    }

    public function resendEmailVerification(Request $request)
    {
        $request->validate([
            'email' => [
                'required',
                'email:rfc,dns,spoof,strict',
            ],
        ]);
        $sEmail = $request->session()->get('user:reg:email');
        $email = $request->input('email');
        abort_if($sEmail != $email, 400, 'Invalid request');
        $sKey = $request->session()->get('user:reg:session_key');
        $res = UserRegisterVerify::whereEmail($email)->where('session_key', $sKey)->firstOrFail();
        abort_if($res->resend_attempts >= config('loops.registration.max_resend_email_verify'), 400, __('common.maxResendLimitReachedPleaseContactSupport'));
        $res->update(['verify_code' => (string) random_int(111111, 999999), 'resend_attempts' => $res->resend_attempts + 1, 'email_last_sent_at' => now()]);
        $request->session()->increment('user:reg:verify_attempts');

        NewAccountEmailVerifyJob::dispatch($res);

        return $this->success();
    }

    public function verifyEmailVerification(Request $request)
    {
        $this->preflightCheck($request);

        $request->validate([
            'email' => [
                'required',
                'email:rfc,dns,spoof,strict',
                'exists:user_register_verifies,email',
            ],
            'code' => 'required|digits:6',
        ]);

        $request->session()->increment('user:reg:verify_attempts');

        if ($request->session()->get('user:reg:verify_attempts') >= 10) {
            return $this->error(__('common.tooManyFailedAttemptsPleaseTryAgainLater'));
        }

        $sEmail = $request->session()->get('user:reg:email');
        $email = $request->input('email');
        abort_if($sEmail != $email, 400, 'Invalid request');
        $sKey = $request->session()->get('user:reg:session_key');
        abort_if(! $sKey, 400, 'Invalid request');

        $code = $request->input('code');
        $reg = UserRegisterVerify::whereEmail($request->email)->whereVerifyCode($code)->first();
        abort_if(! hash_equals($reg->session_key, $sKey), 400, 'Invalid request');

        if (! $reg || $reg->verified_at !== null || $reg->email_last_sent_at === null) {
            return $this->error(__('common.invalidOrExpiredCode'));
        }

        $reg->verified_at = now();
        $reg->save();

        $request->session()->put('user:reg:verified', true);

        return $this->success();
    }

    public function claimUsername(StoreRegisterUsernameRequest $request)
    {
        $this->preflightCheck($request);

        $regId = $request->session()->get('user:reg:id');
        $reg = UserRegisterVerify::whereNotNull('verified_at')->find($regId);

        if (! $reg) {
            return $this->error('Invalid session key');
        }

        $username = $request->username;
        $password = $request->password;

        $user = new User;
        $user->name = $username;
        $user->username = $username;
        $user->email = $reg->email;
        $user->password = Hash::make($password);
        $user->email_verified_at = now();
        $user->save();

        sleep(1);

        $request->session()->regenerate();

        Auth::login($user);

        $reg->delete();

        return $this->success();
    }

    public function preflightCheck($request)
    {
        if ($request->user()) {
            return $this->error('You are already logged in, you must logout before registering a new account.');
        }

        if (config('mail.default') == 'log' && app()->environment() === 'production') {
            return $this->error('Mail service not configured, please contact support for assistance.');
        }

        if (AdminSetting::where('key', 'general.openRegistration')->whereRaw("JSON_EXTRACT(value, '$') = false")->exists()) {
            return $this->error('Registration is closed');
        }
    }
}
