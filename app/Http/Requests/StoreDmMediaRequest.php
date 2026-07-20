<?php

namespace App\Http\Requests;

use App\Rules\KlipyUrl;
use Illuminate\Foundation\Http\FormRequest;

class StoreDmMediaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user();
    }

    public function rules(): array
    {
        return [
            'conversation_id' => 'required_without:recipient_id|nullable|integer',
            'recipient_id' => 'required_without:conversation_id|nullable|integer|exists:profiles,id',
            'body' => 'nullable|string|min:1|max:2000',
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
