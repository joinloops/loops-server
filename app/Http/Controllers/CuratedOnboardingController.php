<?php

namespace App\Http\Controllers;

use App\Http\Requests\SubmitCuratedApplicationRequest;
use App\Models\CuratedApplicationOnboarding;
use App\Models\User;
use App\Rules\HCaptchaRule;
use App\Rules\NotBlockedUsername;
use App\Rules\TurnstileRule;
use App\Rules\ValidUsername;
use App\Services\AccessRuleService;
use App\Services\AdminDashboardService;
use App\Services\CaptchaService;
use App\Services\CuratedOnboardingService;
use Dedoc\Scramble\Attributes\ExcludeAllRoutesFromDocs;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

#[ExcludeAllRoutesFromDocs]
class CuratedOnboardingController extends Controller
{
    public function __construct(
        private CuratedOnboardingService $service
    ) {}

    public function config(): JsonResponse
    {
        return response()->json($this->service->getPublicConfig());
    }

    public function apply(SubmitCuratedApplicationRequest $request): JsonResponse
    {
        if (! $this->service->isEnabled()) {
            return response()->json([
                'message' => 'Registration is not currently accepting applications.',
            ], 403);
        }

        $formLoadedAt = $request->input('_loaded_at');
        if ($formLoadedAt && (now()->timestamp - $formLoadedAt) < 3) {
            return response()->json([
                'message' => 'Your application has been submitted. Check your email for a verification link.',
            ], 201);
        }

        $application = $this->service->submit(
            $request->validated(),
            $request->ip()
        );

        return response()->json([
            'message' => 'Your application has been submitted. Check your email for a verification link.',
            'status' => $application->status,
        ], 201);
    }

    public function verifyEmail(Request $request): JsonResponse
    {
        if (! $this->service->isEnabled()) {
            return response()->json([
                'message' => 'Registration is not currently accepting applications.',
            ], 403);
        }

        $hasCaptcha = config('captcha.enabled');

        $rules = [
            'token' => ['required', 'string', 'size:64'],
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

        $application = $this->service->verifyEmail($request->token);

        app(AdminDashboardService::class)->getReportsCount(true);

        if (! $application) {
            return response()->json([
                'message' => 'Invalid or expired verification token.',
            ], 404);
        }

        return response()->json([
            'message' => 'Email verified successfully. Your application is now being reviewed.',
        ]);
    }

    public function verifyInvite(Request $request): JsonResponse
    {
        if (! $this->service->isEnabled()) {
            return response()->json([
                'message' => 'Registration is not currently accepting applications.',
            ], 403);
        }

        $hasCaptcha = config('captcha.enabled');

        $rules = [
            'email' => ['required', 'email'],
            'key' => ['required', 'string', 'size:35'],
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

        $validated = $request->validate($rules);

        $application = CuratedApplicationOnboarding::where('email', $validated['email'])
            ->where('magic_key', $validated['key'])
            ->where('expires_after', '>', now())
            ->whereNull('joined_at')
            ->first();

        if (! $application) {
            return response()->json([
                'message' => 'Invalid or expired invitation link.',
            ], 404);
        }

        if ($application->joined_at) {
            return response()->json([
                'message' => 'This invitation has already been used.',
            ], 410);
        }

        $onboardingToken = Str::random(64);

        Cache::put(
            "onboarding_token:{$onboardingToken}",
            [
                'email' => $validated['email'],
                'key' => $validated['key'],
                'application_id' => $application->id,
            ],
            now()->addMinutes(30)
        );

        return response()->json([
            'message' => 'Invitation verified successfully.',
            'onboarding_token' => $onboardingToken,
            'username_requested' => $application->username_requested,
        ]);
    }

    public function usernameCheck(Request $request): JsonResponse
    {
        if (! $this->service->isEnabled()) {
            return response()->json([
                'message' => 'Registration is not currently accepting applications.',
            ], 403);
        }

        $validated = $request->validate([
            'onboarding_token' => ['required', 'string', 'size:64'],
            'username' => ['required', new ValidUsername, 'string', 'min:3', 'max:24'],
        ]);

        $tokenData = Cache::get("onboarding_token:{$validated['onboarding_token']}");

        if (! $tokenData) {
            return response()->json([
                'message' => 'Session expired. Please start over.',
            ], 401);
        }

        $exists = User::where('username', $validated['username'])->exists();
        $blocked = app(AccessRuleService::class)->isUsernameBlocked($validated['username']);

        return response()->json([
            'available' => ! $exists && ! $blocked,
        ]);
    }

    public function completeOnboarding(Request $request): JsonResponse
    {
        if (! $this->service->isEnabled()) {
            return response()->json([
                'message' => 'Registration is not currently accepting applications.',
            ], 403);
        }

        $validated = $request->validate([
            'onboarding_token' => ['required', 'string', 'size:64'],
            'username' => ['required', new ValidUsername, 'string', 'min:3', 'max:24', 'unique:users,username', new NotBlockedUsername],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $tokenData = Cache::get("onboarding_token:{$validated['onboarding_token']}");

        if (! $tokenData) {
            return response()->json([
                'message' => 'Session expired. Please start over.',
            ], 401);
        }

        $application = CuratedApplicationOnboarding::with('application')
            ->where('id', $tokenData['application_id'])
            ->where('email', $tokenData['email'])
            ->where('magic_key', $tokenData['key'])
            ->whereNull('joined_at')
            ->first();

        if (! $application) {
            return response()->json([
                'message' => 'Invalid or expired invitation link.',
            ], 404);
        }

        if ($application->joined_at) {
            return response()->json([
                'message' => 'This invitation has already been used.',
            ], 410);
        }

        app(AccessRuleService::class)->enforce($request, AccessRuleService::CONTEXT_REGISTER, $application->email, null, 'username');

        $user = DB::transaction(function () use ($validated, $application) {
            $user = User::create([
                'name' => $validated['username'],
                'username' => $validated['username'],
                'email' => $application->email,
                'password' => Hash::make($validated['password']),
                'email_verified_at' => now(),
                'register_ip' => request()->ip(),
                'birth_date' => Crypt::decryptString($application->application->birthdate_encrypted),
                'last_ip' => request()->ip(),
                'register_source' => 'curated',
            ]);

            $application->update([
                'joined_at' => now(),
            ]);

            $application->application->update([
                'user_id' => $user->id,
            ]);

            return $user;
        });

        Cache::forget("onboarding_token:{$validated['onboarding_token']}");

        Auth::login($user);

        return response()->json([
            'message' => 'Account created successfully.',
            'redirect' => '/',
        ]);
    }

    protected function validateOnboardingToken(string $token): ?array
    {
        return Cache::get("onboarding_token:{$token}");
    }
}
