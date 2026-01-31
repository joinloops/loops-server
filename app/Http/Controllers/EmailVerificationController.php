<?php

namespace App\Http\Controllers;

use App\Http\Requests\EmailManualVerificationRequest;
use App\Jobs\Auth\NewAccountEmailVerifyJob;
use App\Models\User;
use App\Models\UserRegisterVerify;
use App\Support\VerificationCode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class EmailVerificationController extends Controller
{
    /**
     * Send verification code for a user (can be called from other controllers)
     *
     * Checks if there's already a pending (unverified) code for this user.
     * If true, resends the same code (in case original was lost/spam).
     * If false, creates a new code and sends the verification email.
     *
     * @return bool True if a new code was created, false if existing code was resent
     */
    public static function sendVerificationCode(User $user, Request $request): bool
    {
        // Check for existing pending verification code
        $existing = UserRegisterVerify::whereEmail($user->email)
            ->whereNull('verified_at')
            ->latest()
            ->first();

        if ($existing) {
            // Resend existing code (in case it was lost or caught in spam)
            $request->session()->put('user:reg:session_key', $existing->session_key);
            $request->session()->put('user:reg:email', $existing->email);
            $request->session()->put('user:reg:id', $existing->id);

            NewAccountEmailVerifyJob::dispatch($existing);

            return false;
        }

        // Create new verification code and send email
        $sessionKey = Str::random(32);
        $reg = new UserRegisterVerify;
        $reg->session_key = $sessionKey;
        $reg->email = $user->email;
        $reg->verify_code = (string) app(VerificationCode::class)->generate();
        $reg->ip_address = $request->ip();
        $reg->user_agent = $request->userAgent();
        $reg->email_last_sent_at = now();
        $reg->save();

        $request->session()->put('user:reg:session_key', $sessionKey);
        $request->session()->put('user:reg:email', $reg->email);
        $request->session()->put('user:reg:id', $reg->id);

        NewAccountEmailVerifyJob::dispatch($reg);

        return true;
    }

    /**
     * Initiate email verification - send code
     */
    public function initiate(EmailManualVerificationRequest $request)
    {
        $key = 'email-verification:'.$request->ip();

        if (RateLimiter::tooManyAttempts($key, 5)) {
            $seconds = RateLimiter::availableIn($key);

            throw ValidationException::withMessages([
                'email' => ['Too many verification attempts. Please try again in '.ceil($seconds / 60).' minutes.'],
            ]);
        }

        RateLimiter::hit($key, 3600);

        $user = User::where('email', $request->email)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            usleep(random_int(100000, 300000));

            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        if ($user->email_verified_at) {
            return response()->json([
                'message' => 'Email is already verified.',
            ], 400);
        }

        self::sendVerificationCode($user, $request);

        return response()->json([
            'message' => 'Verification code sent to your email.',
            'expires_in' => 900,
        ]);
    }

    /**
     * Confirm email verification with code
     */
    public function confirm(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'code' => ['required', 'string', 'regex:/^\d{6}$/'],
        ]);

        $key = 'email-verification-confirm:'.$request->ip();

        if (RateLimiter::tooManyAttempts($key, 10)) {
            $seconds = RateLimiter::availableIn($key);

            throw ValidationException::withMessages([
                'code' => ['Too many verification attempts. Please try again in '.ceil($seconds / 60).' minutes.'],
            ]);
        }

        RateLimiter::hit($key, 3600);

        $sId = $request->session()->get('user:reg:id');
        $sEmail = $request->session()->get('user:reg:email');
        $sKey = $request->session()->get('user:reg:session_key');

        if (! $sEmail || ! $sKey || ! $sId || $sEmail != $request->email) {
            throw ValidationException::withMessages([
                'code' => ['Invalid or expired verification code.'],
            ]);
        }

        $user = User::where('email', $request->email)->first();

        if (! $user) {
            throw ValidationException::withMessages([
                'email' => ['User not found.'],
            ]);
        }

        $verify = UserRegisterVerify::where('session_key', $sKey)->find($sId);

        if (! $verify || ! hash_equals($verify->verify_code, $request->code)) {
            usleep(random_int(100000, 300000));

            throw ValidationException::withMessages([
                'email' => ['Invalid or expired verification code.'],
            ]);
        }

        $user->email_verified_at = now();
        $user->save();

        $verify->verified_at = now();
        $verify->save();

        RateLimiter::clear($key);

        return response()->json([
            'message' => 'Email verified successfully.',
        ]);
    }

    /**
     * Resend verification code
     */
    public function resend(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        $key = 'email-verification-resend:'.$request->email;

        if (RateLimiter::tooManyAttempts($key, 3)) {
            $seconds = RateLimiter::availableIn($key);

            throw ValidationException::withMessages([
                'email' => ['Too many resend attempts. Please try again in '.ceil($seconds / 60).' minutes.'],
            ]);
        }

        RateLimiter::hit($key, 3600);

        $sId = $request->session()->get('user:reg:id');
        $sEmail = $request->session()->get('user:reg:email');
        $sKey = $request->session()->get('user:reg:session_key');

        if (! $sEmail || ! $sKey || ! $sId || $sEmail != $request->email) {
            throw ValidationException::withMessages([
                'code' => ['Invalid or expired verification code.'],
            ]);
        }

        $user = User::where('email', $request->email)->first();

        if (! $user) {
            // Don't reveal if user exists
            usleep(random_int(100000, 300000));

            return response()->json([
                'message' => 'If this email exists, a new verification code has been sent.',
            ]);
        }

        if ($user->email_verified_at) {
            return response()->json([
                'message' => 'Email is already verified.',
            ], 400);
        }

        $verify = UserRegisterVerify::where('session_key', $sKey)->find($sId);

        if ($verify->resend_attempts > 3) {
            return response()->json([
                'message' => __('common.maxResendLimitReachedPleaseContactSupport'),
            ], 422);
        }

        $verify->update([
            'verify_code' => (string) app(VerificationCode::class)->generate(),
            'resend_attempts' => $verify->resend_attempts + 1,
            'email_last_sent_at' => now(),
        ]);

        NewAccountEmailVerifyJob::dispatch($verify);

        return response()->json([
            'message' => 'New verification code sent to your email.',
        ]);
    }

    /**
     * Check if there's a pending email verification session
     */
    public function status(Request $request)
    {
        $sEmail = $request->session()->get('user:reg:email');
        $sKey = $request->session()->get('user:reg:session_key');
        $sId = $request->session()->get('user:reg:id');

        if (! $sEmail || ! $sKey || ! $sId) {
            return response()->json([
                'has_pending_verification' => false,
            ]);
        }

        // Verify the session is still valid
        $verify = UserRegisterVerify::where('session_key', $sKey)
            ->whereNull('verified_at')
            ->find($sId);

        if (! $verify) {
            return response()->json([
                'has_pending_verification' => false,
            ]);
        }

        // Mask the email for privacy (show first 2 chars and domain)
        $emailParts = explode('@', $sEmail);
        $maskedEmail = substr($emailParts[0], 0, 2).'***@'.$emailParts[1];

        return response()->json([
            'has_pending_verification' => true,
            'email' => $sEmail,
            'masked_email' => $maskedEmail,
        ]);
    }
}
