<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Pagination\CursorPaginator;

class SearchResultResource extends JsonResource
{
    public static $wrap = null;

    public function toArray(Request $request): array
    {
        $hashtags = $this->resource['hashtags'] ?? collect();
        $videos = $this->resource['videos'] ?? collect();
        $users = $this->resource['users'] ?? collect();
        /** @var CursorPaginator|null $pager */
        $pager = $this->resource['pager'] ?? null;

        return [
            'data' => [
                'hashtags' => HashtagResource::collection($hashtags),
                'users' => AccountResource::collection($users),
                'videos' => VideoCompactResource::collection($videos),
            ],
            'links' => [
                'first' => null,
                'last' => null,
                'prev' => null,
                'next' => null,
            ],
            'meta' => [
                'path' => $request->url(),
                'per_page' => $pager['limit'],
                'next_cursor' => $pager['next_cursor'] ?? null,
                'prev_cursor' => $pager['prev_cursor'] ?? null,
            ],
        ];
    }
}
