<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreatePlaylistRequest;
use App\Http\Resources\PlaylistResource;
use App\Http\Resources\PlaylistVideoResource;
use App\Models\Playlist;
use App\Models\PlaylistVideo;
use App\Models\Video;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class PlaylistController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index(Request $request)
    {
        $profile = $request->user()->profile;

        $query = Playlist::where('profile_id', $profile->id);

        if ($request->filled('search')) {
            $preSearch = trim($request->input('search'));
            $search = str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $preSearch);
            $like = '%'.$search.'%';
            $query->where(function ($q) use ($like) {
                $q->where('name', 'like', $like)
                    ->orWhere('description', 'like', $like);
            });
        }

        $sortField = $request->input('sort_field', 'created_at');
        $sortDirection = $request->input('sort_direction', 'desc');
        $query->orderBy($sortField, $sortDirection);

        $perPage = $request->input('limit', 10);
        $playlists = $query->cursorPaginate($perPage);

        return PlaylistResource::collection($playlists);
    }

    public function store(CreatePlaylistRequest $request)
    {
        $validated = $request->validated();

        $playlist = $request->user()->profile->playlists()->create($validated);

        return new PlaylistResource($playlist);
    }

    public function show(Request $request, Playlist $playlist)
    {
        $viewer = $request->user()?->profile;

        if (! $playlist->isVisibleTo($viewer)) {
            abort(403, 'You do not have permission to view this playlist.');
        }

        return new PlaylistResource($playlist);
    }

    public function videos(Request $request, Playlist $playlist)
    {
        $request->validate([
            'limit' => 'sometimes|int|min:1|max:10',
        ]);

        $viewer = $request->user()?->profile;

        if (! $playlist->isVisibleTo($viewer)) {
            abort(403, 'You do not have permission to view this playlist.');
        }

        $perPage = $request->input('limit', 10);

        $videos = $playlist->videos()->cursorPaginate($perPage)->withQueryString();

        return PlaylistVideoResource::collection($videos);
    }

    public function update(Request $request, Playlist $playlist)
    {
        Gate::authorize('update', $playlist);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:30',
            'description' => 'nullable|string|max:1000',
            'visibility' => 'sometimes|in:public,unlisted,private,followers',
        ]);

        $playlist->update($validated);
        $playlist->updateProfileHasPlaylists();

        return new PlaylistResource($playlist);
    }

    public function destroy(Request $request, Playlist $playlist)
    {
        Gate::authorize('delete', $playlist);

        $playlist->delete();

        $profile = $request->user()->profile;

        $exists = Playlist::whereProfileId($profile->id)
            ->where('visibility', 'public')
            ->has('videos', '>', 1)
            ->exists();

        $profile->update(['has_playlists' => $exists]);

        return response()->json(null, 204);
    }

    public function addVideo(Request $request, Playlist $playlist)
    {
        Gate::authorize('update', $playlist);

        $validated = $request->validate([
            'video_id' => 'required|exists:videos,id',
            'position' => 'nullable|integer|min:0',
        ]);

        $video = Video::published()->findOrFail($validated['video_id']);

        if ($video->profile_id != $playlist->profile_id) {
            abort(403, 'You can only add your own videos to playlists.');
        }

        $existsInOtherPlaylists = PlaylistVideo::where('video_id', $video->id)->exists();

        if ($existsInOtherPlaylists) {
            abort(403, 'Video already exists in another playlist.');
        }

        $playlist->addVideo($video, $validated['position'] ?? null);

        $playlist->update(['videos_count' => $playlist->videos()->count()]);

        return response()->json(['message' => 'Video added to playlist']);
    }

    public function removeVideo(Playlist $playlist, Video $video)
    {
        Gate::authorize('update', $playlist);

        $playlist->removeVideo($video);

        $playlist->update(['videos_count' => $playlist->videos()->count()]);

        return response()->json(['message' => 'Video removed from playlist']);
    }

    public function reorder(Request $request, Playlist $playlist)
    {
        Gate::authorize('update', $playlist);

        $validated = $request->validate([
            'video_ids' => 'required|array',
            'video_ids.*' => 'exists:videos,id',
        ]);

        $playlist->reorderVideos($validated['video_ids']);

        return response()->json(['message' => 'Playlist reordered']);
    }
}
