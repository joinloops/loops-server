<?php

namespace App\Http\Requests;

use App\Models\Comment;
use App\Models\Video;
use App\Rules\KlipyUrl;
use Illuminate\Foundation\Http\FormRequest;

class StoreCommentMediaRequest extends FormRequest
{
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
            'comment' => 'nullable|string|min:1|max:500',
            'type' => 'required|string|in:gifs,stickers,memes,clips',
            'item' => 'required|array',
            'item.id' => 'required',
            'item.width' => 'required|integer',
            'item.height' => 'required|integer',
            'item.title' => 'sometimes|nullable|string',
            'item.slug' => 'required|string',
            'item.full.url' => ['sometimes', 'url', new KlipyUrl],
            'item.mp4.url' => ['sometimes', 'url', new KlipyUrl],
            'item.webm.url' => ['sometimes', 'url', new KlipyUrl],
            'item.preview.url' => ['sometimes', 'url', new KlipyUrl],
        ];
    }
}
