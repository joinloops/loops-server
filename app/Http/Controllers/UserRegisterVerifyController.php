<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Api\Traits\ApiHelpers;
use App\Http\Requests\StoreRegisterUsernameRequest;
use App\Http\Requests\StoreUserRegisterVerifyRequest;
use App\Jobs\Auth\NewAccountEmailVerifyJob;
use App\Jobs\User\UserSpamCheckJob;
use App\Models\AdminSetting;
use App\Models\User;
use App\Models\UserRegisterVerify;
use App\Rules\NotBlockedEmail;
use App\Rules\NotBlockedIp;
use App\Services\AccessRuleService;
use App\Services\ConfigService;
use App\Support\VerificationCode;
use Carbon\Carbon;
use Dedoc\Scramble\Attributes\ExcludeAllRoutesFromDocs;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

#[ExcludeAllRoutesFromDocs]
class UserRegisterVerifyController extends Controller
{
    use ApiHelpers;

    public function sendEmailVerification(StoreUserRegisterVerifyRequest $request)
    {
        $email = $request->input('email');

        $existing = UserRegisterVerify::whereEmail($email)
            ->whereNull('verified_at')
            ->latest()
            ->first();

        if ($existing) {
            $sessionKey = Str::random(32);
            $existing->update([
                'session_key' => $sessionKey,
                'verify_code' => (string) app(VerificationCode::class)->generate(),
                'resend_attempts' => 1,
                'email_last_sent_at' => now(),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            $this->establishVerificationSession($request, $existing, $sessionKey);

            NewAccountEmailVerifyJob::dispatch($existing);

            return $this->success();
        }

        $sessionKey = Str::random(32);
        $reg = new UserRegisterVerify;
        $reg->session_key = $sessionKey;
        $reg->email = $email;
        $reg->verify_code = (string) app(VerificationCode::class)->generate();
        $reg->ip_address = $request->ip();
        $reg->user_agent = $request->userAgent();
        $reg->email_last_sent_at = now();
        $reg->save();

        $this->establishVerificationSession($request, $reg, $sessionKey);

        NewAccountEmailVerifyJob::dispatch($reg);

        return $this->success();
    }

    public function resendEmailVerification(Request $request)
    {
        $request->validate([
            'email' => [
                'required',
                new NotBlockedIp,
                'email:rfc,dns,spoof,strict',
                new NotBlockedEmail,
            ],
        ]);

        $email = $request->input('email');
        $sKey = $request->session()->get('user:reg:session_key');

        $res = UserRegisterVerify::whereEmail($email)
            ->whereNull('verified_at')
            ->when($sKey, function ($query) use ($sKey) {
                return $query->where('session_key', $sKey);
            })
            ->latest()
            ->first();

        abort_if(! $res, 404, __('common.noActiveVerificationFound'));

        $this->establishVerificationSession($request, $res);

        abort_if(
            $res->resend_attempts >= config('loops.registration.max_resend_email_verify'),
            400,
            __('common.maxResendLimitReachedPleaseContactSupport')
        );

        $res->update([
            'verify_code' => (string) app(VerificationCode::class)->generate(),
            'resend_attempts' => $res->resend_attempts + 1,
            'email_last_sent_at' => now(),
        ]);

        $request->session()->increment('user:reg:verify_attempts');

        NewAccountEmailVerifyJob::dispatch($res);

        return $this->success();
    }

    private function establishVerificationSession(Request $request, UserRegisterVerify $reg, ?string $sessionKey = null)
    {
        if ($sessionKey) {
            $request->session()->put('user:reg:session_key', $sessionKey);
        } else {
            $request->session()->put('user:reg:session_key', $reg->session_key);
        }

        $request->session()->put('user:reg:email', $reg->email);
        $request->session()->put('user:reg:id', $reg->id);

        if (! $request->session()->has('user:reg:verify_attempts')) {
            $request->session()->put('user:reg:verify_attempts', 0);
        }
    }

    public function verifyEmailVerification(Request $request)
    {
        $this->preflightCheck($request);

        $request->validate([
            'email' => [
                'required',
                new NotBlockedIp,
                'email:rfc,dns,spoof,strict',
            ],
            'code' => 'required|digits:6',
        ]);

        $email = $request->input('email');
        $code = $request->input('code');
        $sEmail = $request->session()->get('user:reg:email');
        $sKey = $request->session()->get('user:reg:session_key');

        $request->session()->increment('user:reg:verify_attempts');

        if ($request->session()->get('user:reg:verify_attempts') >= 10) {
            return $this->error(__('common.tooManyFailedAttemptsPleaseTryAgainLater'));
        }

        $reg = null;

        if ($sEmail === $email && $sKey) {
            $reg = UserRegisterVerify::whereEmail($email)
                ->where('session_key', $sKey)
                ->whereNull('verified_at')
                ->first();
        }

        if (! $reg) {
            $reg = UserRegisterVerify::whereEmail($email)
                ->whereNull('verified_at')
                ->where('email_last_sent_at', '>=', now()->subHours(4))
                ->latest()
                ->first();

            if ($reg) {
                $this->establishVerificationSession($request, $reg);
            }
        }

        if (! $reg) {
            return $this->error(__('common.verificationRecordNotFoundPleaseStartAgain'));
        }

        if ($reg->verified_at !== null) {
            return $this->error(__('common.emailAlreadyVerified'));
        }

        if ($reg->email_last_sent_at === null) {
            return $this->error(__('common.noVerificationCodeSent'));
        }

        if ($reg->email_last_sent_at->lt(now()->subHour())) {
            return $this->error(__('common.verificationCodeExpiredPleaseRequestNew'));
        }

        if ($reg->verify_code !== $code) {
            return $this->error(__('common.invalidVerificationCode'));
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

        app(AccessRuleService::class)->enforce($request, AccessRuleService::CONTEXT_REGISTER, $reg->email, null, 'username');

        $username = $request->username;
        $password = $request->password;
        $birthDate = $request->birth_date;

        $user = new User;
        $user->name = $username;
        $user->username = $username;
        $user->email = $reg->email;
        $user->password = Hash::make($password);
        $user->email_verified_at = now();
        $user->birth_date = $birthDate;
        $user->register_ip = $request->ip();
        $user->last_ip = $request->ip();
        $user->register_source = $request->filled('mobile') ? 'mobile' : 'web';
        $user->save();

        sleep(1);

        $request->session()->regenerate();

        Auth::login($user);

        if (app(ConfigService::class)->userSpamDetection()) {
            UserSpamCheckJob::dispatch($user);
        }

        $reg->delete();

        if ($request->filled('mobile')) {
            $token = $user->createToken('mobile-app')->accessToken;

            return $this->data([
                'mobile' => true,
                'token' => $token,
                'user' => [
                    'id' => $user->id,
                    'username' => $user->username,
                ],
            ]);
        }

        return $this->success();
    }

    public function verifyAge(Request $request)
    {
        $data = $request->validate([
            'birthdate' => 'required|date|before:today',
        ]);

        $minAge = config('loops.registration.min_years_old', 16);
        $age = Carbon::parse($data['birthdate'])->diffInYears(now());
        $allowed = $age >= $minAge;

        return response()->json([
            'data' => [
                'allowed' => $allowed,
                'minAge' => $minAge,
                'message' => $allowed
                    ? __('common.birthdateVerified')
                    : __('common.youMustBeAtLeastXYearsOld', ['years' => $minAge]),
            ],
        ]);
    }

    public function verifyUserAndSendEmailVerification(Request $request)
    {
        $validated = $request->validate([
            'email' => 'required|string',
            'password' => 'required|string',
        ]);

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
