<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Models\Video;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class UpdateVideoRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        if(!$this->user()) {
            return false;
        }
        $video = Video::whereStatus(2)->find($this->route('id'));
        return $video && $this->user()->can('update', $video);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'caption' => 'nullable|string|max:200',
            'can_download' => 'nullable|boolean',
            'can_comment' => 'nullable|boolean',
            'is_pinned' => 'nullable|boolean',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $fields = ['can_download', 'can_comment', 'is_pinned'];
        foreach ($fields as $key) {
            if ($this->has($key)) {
                $this->merge([
                    $key => $this->toBoolean($this->input($key)),
                ]);
            }
        }
    }

    /**
     * Converts a value to a boolean.
     * Handles 'true', 'false', 1, 0, '1', '0', true, false.
     *
     * @param mixed $value
     * @return bool|mixed
     */
    private function toBoolean($value)
    {
        if (is_string($value)) {
            $value = strtolower($value);
            if (in_array($value, ['true', '1'])) {
                return true;
            }
            if (in_array($value, ['false', '0'])) {
                return false;
            }
        }

        if (is_int($value) && in_array($value, [0, 1])) {
            return (bool) $value;
        }

        return $value;
    }
}
