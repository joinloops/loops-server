<?php

namespace App\Http\Requests;

use App\Models\Video;
use App\Services\IntlService;
use Cache;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;

class StoreDuetRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        if (! $this->user() || ! $this->user()->can_upload) {
            return false;
        }

        if (config('loops.uploads.rate_limits.per_day')) {
            $limit = (int) config('loops.uploads.rate_limits.per_day');
            if (
                Video::whereProfileId($this->user()->profile_id)
                    ->where('created_at', '>', now()->subHours(24))
                    ->count() >= $limit
            ) {
                return false;
            }
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
        $config = Cache::get('settings:public');
        $maxSize = data_get($config, 'media.max_video_size', 40);

        return [
            'video' => [
                'required',
                File::types(['mp4'])
                    ->min(250)
                    ->max($maxSize * 1024),
            ],
            'duet_id' => 'required|exists:videos,id',
            'duet_layout' => 'required|string|in:side-by-side,vertical',
            'description' => 'nullable|string|max:200',
            'comment_state' => 'sometimes|string|in:0,4',
            'can_download' => 'nullable|boolean',
            'can_comment' => 'nullable|boolean',
            'can_duet' => 'nullable|boolean',
            'can_stitch' => 'nullable|boolean',
            'is_sensitive' => 'nullable|boolean',
            'alt_text' => 'nullable|sometimes|string|max:2000',
            'contains_ai' => 'nullable|boolean',
            'contains_ad' => 'nullable|boolean',
            'lang' => [
                'sometimes',
                'string',
                Rule::in(app(IntlService::class)->keys()),
            ],
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $fields = ['can_download', 'can_comment', 'can_duet', 'can_stitch', 'is_sensitive', 'contains_ai', 'contains_ad'];
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
