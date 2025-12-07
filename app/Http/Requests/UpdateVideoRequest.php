<?php

namespace App\Http\Requests;

use App\Models\Video;
use App\Services\IntlService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateVideoRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        if (! $this->user()) {
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
            'can_duet' => 'nullable|boolean',
            'can_stitch' => 'nullable|boolean',
            'is_pinned' => 'nullable|boolean',
            'alt_text' => 'nullable|sometimes|string|max:2000',
            'is_sensitive' => 'nullable|boolean',
            'contains_ai' => 'nullable|boolean',
            'contains_ad' => 'nullable|boolean',
            'lang' => [
                'nullable',
                'sometimes',
                'string',
                Rule::in(app(IntlService::class)->keys()),
            ],
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($this->input('is_pinned') === true) {
                $video = Video::find($this->route('id'));

                if ($video && ! $video->is_pinned) {
                    $pinnedCount = Video::where('profile_id', $this->user()->profile_id)
                        ->where('status', 2)
                        ->where('is_pinned', true)
                        ->count();

                    if ($pinnedCount >= 3) {
                        $validator->errors()->add(
                            'is_pinned',
                            'You can only pin up to 3 videos. Please unpin another video first.'
                        );
                    }
                }
            }
        });
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $fields = ['can_download', 'can_comment', 'is_pinned', 'can_duet', 'can_stitch', 'is_sensitive', 'contains_ai', 'contains_ad'];
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
     * @param  mixed  $value
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
