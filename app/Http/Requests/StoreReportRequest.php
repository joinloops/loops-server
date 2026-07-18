<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreReportRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        if (! $this->user()) {
            return false;
        }

        if ($this->user()->trust_level < 5) {
            return false;
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
            'type' => 'required|in:video,profile,comment,reply,hashtag,starter_kit,sound',
            'id' => 'required|string',
            'key' => 'required|integer|min:1009|max:1026',
            'comment' => 'sometimes|nullable|string|min:1|max:500',
        ];
    }
}
