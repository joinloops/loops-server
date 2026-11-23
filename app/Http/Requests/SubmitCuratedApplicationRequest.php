<?php

namespace App\Http\Requests;

use App\Models\CuratedApplicationSettings;
use App\Rules\HCaptchaRule;
use App\Rules\NotBlockedEmail;
use App\Rules\NotBlockedIp;
use App\Rules\NotBlockedUsername;
use App\Rules\TurnstileRule;
use App\Rules\ValidUsername;
use App\Services\CaptchaService;
use Illuminate\Foundation\Http\FormRequest;

class SubmitCuratedApplicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Public endpoint
    }

    public function rules(): array
    {
        $hasCaptcha = config('captcha.enabled');

        $rules = [
            'email' => ['required', new NotBlockedIp, 'email:rfc,dns,spoof,strict', new NotBlockedEmail, 'max:255', 'unique:users', 'unique:curated_applications'],
            'username_requested' => ['nullable', new ValidUsername, 'string', 'min:3', 'max:24', 'unique:users,username', new NotBlockedUsername],
            'birthdate' => ['required', 'date', 'before:today', 'after:1900-01-01'],
            'reason' => ['required', 'string', 'min:10', 'max:2000'],
            'fediverse_account' => ['nullable', 'string', 'max:255', 'regex:/^@?[\w.-]+@[\w.-]+\.\w+$/'],
            'custom_answers' => ['nullable', 'array'],
            'custom_answers.*' => ['nullable', 'string', 'max:1000'],
            // Honeypot field — must be empty
            'website' => ['nullable', 'max:0'],
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

        $settings = CuratedApplicationSettings::current();

        foreach ($settings->questions_list as $index => $question) {
            $key = "custom_answers.{$question['id']}";

            if (! empty($question['required'])) {
                $rules[$key] = ['required', 'string', 'max:1000'];
            }

            if ($question['type'] === CuratedApplicationSettings::QUESTION_TYPE_SELECT && ! empty($question['options'])) {
                $rules[$key] = array_merge(
                    $rules[$key] ?? ['nullable', 'string'],
                    ['in:'.implode(',', $question['options'])]
                );
            }
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'birthdate.before' => 'Please enter a valid birthdate.',
            'reason.min' => 'Please write at least a few sentences about why you want to join.',
            'fediverse_account.regex' => 'Please enter a valid fediverse handle (e.g., @user@mastodon.social).',
            'website.max' => '',
        ];
    }
}
