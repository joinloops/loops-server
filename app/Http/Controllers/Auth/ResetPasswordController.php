<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Rules\HCaptchaRule;
use App\Rules\TurnstileRule;
use App\Services\CaptchaService;
use Illuminate\Foundation\Auth\ResetsPasswords;
use Illuminate\Validation\Rules;

class ResetPasswordController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Password Reset Controller
    |--------------------------------------------------------------------------
    |
    | This controller is responsible for handling password reset requests
    | and uses a simple trait to include this behavior. You're free to
    | explore this trait and override any methods you wish to tweak.
    |
    */

    use ResetsPasswords;

    /**
     * Where to redirect users after resetting their password.
     *
     * @var string
     */
    protected $redirectTo = '/';

    /**
     * Get the password reset validation rules.
     *
     * @return array
     */
    protected function rules()
    {
        $hasCaptcha = config('captcha.enabled');

        $rules = [
            'token' => 'required',
            'email' => 'required|email',
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
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
}
