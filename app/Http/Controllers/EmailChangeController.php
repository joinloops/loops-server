<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Api\Traits\ApiHelpers;
use App\Http\Requests\ChangeEmailRequest;
use App\Http\Requests\UpdateEmailPreferencesRequest;
use App\Jobs\Auth\EmailChangeVerificationDeliverJob;
use App\Mail\EmailChangeVerification;
use App\Mail\EmailVerification;
use App\Models\EmailChange;
use App\Models\User;
use App\Services\UserAuditLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class EmailChangeController extends Controller
{
    use ApiHelpers;

    protected UserAuditLogService $auditService;

    public function __construct(UserAuditLogService $auditService)
    {
        $this->middleware('auth');
        $this->auditService = $auditService;
    }

    public function getEmailSettings(Request $request)
    {
        $user = $request->user();
        $pendingChange = EmailChange::where('user_id', $user->id)
            ->where('expires_at', '>', now())
            ->first();

        return $this->data([
            'current_email' => $this->maskEmail($user->email),
            'email_verified' => $user->email_verified_at !== null,
            'email_added_date' => $user->created_at->format('F j, Y'),
            'pending_email' => $pendingChange ? $pendingChange->new_email : null,
        ]);
    }

    private function maskEmail($email)
    {
        [$local, $domain] = explode('@', $email);
        $localLength = strlen($local);

        if ($localLength <= 3) {
            $maskedLocal = substr($local, 0, 1).str_repeat('*', $localLength - 1);
        } elseif ($localLength <= 6) {
            $maskedLocal = substr($local, 0, 2).str_repeat('*', $localLength - 3).substr($local, -1);
        } else {
            $maskedLocal = substr($local, 0, 3).str_repeat('*', max(3, $localLength - 5)).substr($local, -2);
        }

        $domainParts = explode('.', $domain);
        $tld = array_pop($domainParts);

        if (count($domainParts) === 0) {
            $maskedDomain = $tld;
        } else {
            $domainName = implode('.', $domainParts);
            $domainLength = strlen($domainName);

            if ($domainLength <= 3) {
                $maskedDomainName = substr($domainName, 0, 1).str_repeat('*', $domainLength - 1);
            } elseif ($domainLength <= 6) {
                $maskedDomainName = substr($domainName, 0, 2).str_repeat('*', $domainLength - 2);
            } else {
                $maskedDomainName = substr($domainName, 0, 3).str_repeat('*', $domainLength - 3);
            }

            $maskedDomain = $maskedDomainName.'.'.$tld;
        }

        return $maskedLocal.'@'.$maskedDomain;
    }

    public function changeEmail(ChangeEmailRequest $request)
    {
        if (config('mail.default') == 'log') {
            return $this->error('Mail service not configured, please contact support for assistance.', 422);
        }
        $user = $request->user();
        $newEmail = $request->input('email');
        $password = $request->input('password');

        if (! Hash::check($password, $user->password)) {
            return $this->error([
                'errors' => ['password' => ['Current password is incorrect.']],
            ], 422);
        }

        if (User::where('email', $newEmail)->where('id', '!=', $user->id)->exists()) {
            return $this->error([
                'errors' => ['email' => ['This email address is already in use.']],
            ], 422);
        }

        $existingChange = EmailChange::where('user_id', $user->id)
            ->where('expires_at', '>', now())
            ->first();

        if ($existingChange) {
            return $this->error([
                'errors' => ['email' => ['You already have a pending email change. Please complete or cancel it first.']],
            ], 422);
        }

        $token = Str::random(64);
        $emailChange = EmailChange::create([
            'user_id' => $user->id,
            'old_email' => $user->email,
            'new_email' => $newEmail,
            'token' => hash('sha256', $token),
            'expires_at' => now()->addHours(24),
        ]);

        $this->auditService->logEmailChanged($user, $user->email, $newEmail);

        EmailChangeVerificationDeliverJob::dispatch($user, $newEmail, $token, $emailChange->id);

        return $this->success();
    }

    public function verifyEmailChange(Request $request)
    {
        $request->validate([
            'user_id' => 'required|integer|min:1|max:9999999999',
            'token' => 'required|string',
        ]);

        if ($request->input('user_id') != $user->id) {
            return $this->error('Invalid user_id value, please try again', 422);
        }

        $id = $request->input('user_id');
        $token = $request->input('token');

        $hashedToken = hash('sha256', $token);

        $emailChange = EmailChange::where('token', $hashedToken)
            ->where('user_id', $id)
            ->where('expires_at', '>', now())
            ->first();

        if (! $emailChange) {
            return $this->error('Invalid or expired verification link.');
        }

        $user = User::findOrFail($emailChange->user_id);

        $oldEmail = $user->email;
        $user->email = $emailChange->new_email;
        $user->email_verified_at = now();
        $user->save();

        $this->auditService->logEmailVerified($user, ['email' => $emailChange->new_email]);

        $emailChange->delete();

        return $this->data('Email address updated successfully!');
    }

    public function resendEmailChange(Request $request)
    {
        $request->validate([
            'user_id' => 'required|integer|min:1',
        ]);

        if (config('mail.default') == 'log') {
            return $this->error('Mail service not configured, please contact support for assistance.', 422);
        }

        $user = $request->user();

        if ($request->input('user_id') != $user->id) {
            return $this->error('Invalid user_id value, please try again', 422);
        }

        $emailChange = EmailChange::where('user_id', $user->id)
            ->where('expires_at', '>', now())
            ->first();

        if (! $emailChange) {
            return $this->error('No pending email change found.', 404);
        }

        if ($emailChange->created_at->gt(now()->subHours(12))) {
            return $this->error('You can only resend a verification email once per 12 hours.', 422);
        }

        $token = Str::random(64);
        $emailChange->token = hash('sha256', $token);
        $emailChange->expires_at = now()->addHours(24);
        $emailChange->save();

        try {
            Mail::to($emailChange->new_email)->send(new EmailChangeVerification($user, $token));

            return response()->json([
                'message' => 'Verification email resent successfully.',
            ]);

        } catch (\Exception $e) {
            \Log::error('Email change resend failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Failed to resend verification email.',
            ], 500);
        }
    }

    public function cancelEmailChange(Request $request)
    {
        $user = $request->user();

        $deleted = EmailChange::where('user_id', $user->id)->delete();

        if ($deleted) {
            if (config('logging.dev_log')) {
                \Log::info('Email change cancelled', ['user_id' => $user->id]);
            }

            return response()->json([
                'message' => 'Email change cancelled successfully.',
            ]);
        }

        return response()->json([
            'message' => 'No pending email change found.',
        ], 404);
    }

    public function resendVerification(Request $request)
    {
        $user = $request->user();

        if ($user->email_verified_at) {
            return response()->json([
                'message' => 'Email is already verified.',
            ], 422);
        }

        $token = Str::random(64);
        $user->email_verification_token = hash('sha256', $token);
        $user->save();

        try {
            Mail::to($user->email)->send(new EmailVerification($user, $token));

            return response()->json([
                'message' => 'Verification email sent successfully.',
            ]);

        } catch (\Exception $e) {
            if (config('logging.dev_log')) {
                \Log::error('Email verification resend failed', [
                    'user_id' => $user->id,
                    'error' => $e->getMessage(),
                ]);
            }

            return response()->json([
                'message' => 'Failed to send verification email.',
            ], 500);
        }
    }

    public function updatePreferences(UpdateEmailPreferencesRequest $request)
    {
        $user = $request->user();

        // $user->update([
        //     'email_notifications' => $request->input('account_notifications', true),
        //     'product_updates' => $request->input('product_updates', false),
        //     'marketing_emails' => $request->input('marketing_emails', false),
        //     'email_frequency' => $request->input('email_frequency', 'instant'),
        // ]);

        return response()->json([
            'message' => 'Email preferences updated successfully.',
            'preferences' => [
                'security_alerts' => true,
                'account_notifications' => true,
                'product_updates' => false,
                'marketing_emails' => false,
                'email_frequency' => false,
            ],
        ]);
    }
}
