<?php

namespace App\Http\Requests;

use App\Services\AccountService;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

class UpdateProfileRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return (bool) $this->user() &&
               $this->user()->profile->status == 1 &&
               $this->user()->profile_id;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => [
                'sometimes',
                'string',
                'nullable',
                'min:0',
                'max:30',
                function (string $attribute, mixed $value, Closure $fail) {
                    if (! AccountService::validDisplayName($value)) {
                        $fail('The display name you entered contains invalid characters. Please try again with only letters and numbers.');
                    }
                },
            ],
            'bio' => 'sometimes|string|nullable|min:0|max:250',
        ];
    }
}
