<?php

namespace App\Http\Requests;

use App\Models\Comment;
use App\Models\Video;
use Illuminate\Foundation\Http\FormRequest;

class StoreCommentRequest extends FormRequest
{
    public const COMMENT_MAX_LENGTH = 500;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        if (! $this->user() || ! $this->user()->can_comment) {
            return false;
        }
        $video = Video::published()->canComment()->find($this->route('id'));

        return $video && $this->user()->can('create', [Comment::class, $video]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'parent_id' => 'sometimes|integer|exists:comments,id',
            'comment' => 'required|string|min:1|max:'.self::COMMENT_MAX_LENGTH,
        ];
    }
}
