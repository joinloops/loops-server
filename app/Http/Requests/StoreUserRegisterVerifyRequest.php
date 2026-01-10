<?php

namespace App\Http\Requests;

use App\Models\AdminSetting;
use App\Models\UserRegisterVerify;
use App\Rules\HCaptchaRule;
use App\Rules\TurnstileRule;
use App\Services\CaptchaService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreUserRegisterVerifyRequest extends FormRequest
{
    public $denyMessage;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        if ($this->user()) {
            throw new AuthorizationException('You are already logged in, you must logout before registering a new account.');
        }

        if (config('mail.default') == 'log' && app()->environment() === 'production') {
            throw new AuthorizationException('Mail service not configured, please contact support for assistance.');
        }

        if (AdminSetting::where('key', 'general.openRegistration')->whereRaw("JSON_EXTRACT(value, '$') = false")->exists()) {
            throw new AuthorizationException('Registration is disabled, please try again later.');
        }

        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $hasCaptcha = config('captcha.enabled');
        $rules = [
            'email' => [
                'required',
                'email:rfc,dns,spoof,strict',
                'unique:users,email',
            ],
        ];

        if ($hasCaptcha) {
            $driver = config('captcha.driver');
            
            if (!in_array($driver, ['turnstile', 'hcaptcha'])) {
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

        return $rules;
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($validator) {
            $email = $this->input('email');

            $recentVerification = UserRegisterVerify::whereEmail($email)
                ->where('email_last_sent_at', '>=', now()->subMinutes(2))
                ->whereNull('verified_at')
                ->first();

            if ($recentVerification) {
                $validator->errors()->add(
                    'email',
                    __('common.verificationCodeRecentlySentPleaseCheckYourEmail')
                );
            }
        });
    }
}
