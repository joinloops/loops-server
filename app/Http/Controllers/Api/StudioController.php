<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Traits\ApiHelpers;
use App\Http\Controllers\Controller;
use App\Http\Resources\StudioVideoResource;
use App\Models\Video;
use App\Services\VideoService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class StudioController extends Controller
{
    use ApiHelpers;

    public function __construct()
    {
        $this->middleware('auth');
    }

    public function getPosts(Request $request)
    {
        $validated = $request->validate([
            'search' => ['sometimes', 'nullable', 'string', 'min:2', 'max:30'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:10'],
            'sort_field' => ['sometimes', 'string', Rule::in(['created_at'])],
            'sort_direction' => ['sometimes', 'string', Rule::in(['asc', 'desc'])],
        ]);

        $pid = $request->user()->profile_id;
        $search = $validated['search'] ?? null;
        $limit = $validated['limit'] ?? 10;
        $sortBy = $validated['sort_field'] ?? 'created_at';
        $sortDir = $validated['sort_direction'] ?? 'desc';

        if ($search !== null && $search !== '') {
            $search = $this->escapeLike($search);
        }

        $total = VideoService::totalUserCount($pid, false);

        $videos = Video::whereProfileId($pid)
            ->published()
            ->when($search, function ($query, $search) {
                $query->where('caption', 'like', "%{$search}%");
            })
            ->orderBy($sortBy, $sortDir)
            ->cursorPaginate($limit)
            ->withQueryString();

        return StudioVideoResource::collection($videos)
            ->additional([
                'meta' => [
                    'total_videos' => $total,
                ],
            ]);
    }

    public function getAvailableVideosForPlaylists(Request $request)
    {
        $validated = $request->validate([
            'search' => ['sometimes', 'nullable', 'string', 'min:2', 'max:30'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:10'],
            'sort_field' => ['sometimes', 'string', Rule::in(['created_at'])],
            'sort_direction' => ['sometimes', 'string', Rule::in(['asc', 'desc'])],
        ]);

        $pid = $request->user()->profile_id;
        $search = $validated['search'] ?? null;
        $limit = $validated['limit'] ?? 10;
        $sortBy = $validated['sort_field'] ?? 'created_at';
        $sortDir = $validated['sort_direction'] ?? 'desc';

        if ($search !== null && $search !== '') {
            $search = $this->escapeLike($search);
        }

        $videos = Video::select('videos.*')
            ->published()
            ->leftJoin('playlist_video', 'videos.id', '=', 'playlist_video.video_id')
            ->whereNull('playlist_video.video_id')
            ->where('videos.profile_id', $pid)
            ->when($search, function ($query, $search) {
                $query->where('videos.caption', 'like', "%{$search}%");
            })
            ->orderBy("videos.{$sortBy}", $sortDir)
            ->cursorPaginate($limit)
            ->withQueryString();

        return StudioVideoResource::collection($videos);
    }

    protected function escapeLike(string $value, string $escapeChar = '\\'): string
    {
        return str_replace(
            [$escapeChar, '%', '_'],
            [$escapeChar.$escapeChar, $escapeChar.'%', $escapeChar.'_'],
            $value
        );
    }
}
