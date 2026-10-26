<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAccountPassword extends FormRequest
{
    public const MIN_LENGTH = 8;

    public const MAX_LENGTH = 72;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return (bool) $this->user();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'current_password' => 'required|current_password',
            'password' => 'required|string|min:'.self::MIN_LENGTH.'|max:'.self::MAX_LENGTH.'|confirmed',
            'password_confirmation' => 'required|string|min:'.self::MIN_LENGTH.'|max:'.self::MAX_LENGTH,
        ];
    }
}
