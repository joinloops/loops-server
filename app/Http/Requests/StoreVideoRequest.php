<?php

namespace App\Http\Requests;

use App\Models\Video;
use App\Rules\HasVideoStream;
use App\Services\IntlService;
use Cache;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;

class StoreVideoRequest extends FormRequest
{
    public const CAPTION_MAX_LENGTH = 200;

    public const ALT_TEXT_MAX_LENGTH = 2000;

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
                'bail',
                'required',
                File::types(config('loops.media.video_types', ['mp4']))
                    ->min(250)
                    ->max($maxSize * 1024),
                new HasVideoStream,
            ],
            'thumbnail' => [
                'nullable',
                'sometimes',
                File::image()
                    ->types(['jpg', 'jpeg', 'png', 'webp'])
                    ->max(5 * 1024)
                    ->dimensions(Rule::dimensions()->width(1080)->height(1920)),
            ],
            'description' => 'nullable|string|max:'.self::CAPTION_MAX_LENGTH,
            'comment_state' => 'sometimes|string|in:0,4',
            'can_download' => 'nullable|boolean',
            'can_comment' => 'nullable|boolean',
            'can_duet' => 'nullable|boolean',
            'can_stitch' => 'nullable|boolean',
            'can_embed' => 'nullable|boolean',
            'is_sensitive' => 'nullable|boolean',
            'alt_text' => 'nullable|sometimes|string|max:'.self::ALT_TEXT_MAX_LENGTH,
            'contains_ai' => 'nullable|boolean',
            'contains_ad' => 'nullable|boolean',
            'scheduled_at' => 'sometimes|nullable|date',
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
        $fields = ['can_download', 'can_comment', 'can_duet', 'can_stitch', 'can_embed', 'is_sensitive', 'contains_ai', 'contains_ad'];
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
