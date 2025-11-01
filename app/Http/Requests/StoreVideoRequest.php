<?php

namespace App\Http\Requests;

use App\Models\Video;
use App\Services\IntlService;
use Cache;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;

class StoreVideoRequest extends FormRequest
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
            'description' => 'nullable|string|max:200',
            'comment_state' => 'sometimes|string|in:0,4',
            'can_download' => 'sometimes',
            'is_sensitive' => 'sometimes|boolean',
            'alt_text' => 'nullable|sometimes|string|max:2000',
            'contains_ai' => 'sometimes|boolean',
            'contains_ad' => 'sometimes|boolean',
            'lang' => [
                'sometimes',
                'string',
                Rule::in(app(IntlService::class)->keys()),
            ],
        ];
    }
}
