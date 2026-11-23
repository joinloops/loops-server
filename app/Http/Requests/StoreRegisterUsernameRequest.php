<?php

namespace App\Http\Requests;

use App\Models\AdminSetting;
use App\Rules\NotBlockedUsername;
use App\Rules\ValidUsername;
use App\Services\UsernameService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Http\FormRequest;

class StoreRegisterUsernameRequest extends FormRequest
{
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
        return [
            'username' => [
                'required',
                'min:2',
                'max:24',
                'unique:users,username',
                new ValidUsername,
                function ($attribute, $value, $fail) {
                    if (app(UsernameService::class)->isReserved($value)) {
                        $fail('This username is reserved.');
                    }
                },
                new NotBlockedUsername,
            ],
            'password' => 'required|min:8',
            'password_confirmation' => 'required|confirmed:password',
            'birth_date' => 'required|date|before:today',
        ];
    }
}
