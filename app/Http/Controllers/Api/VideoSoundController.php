<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Traits\ApiHelpers;
use App\Http\Controllers\Controller;
use App\Http\Resources\VideoCompactResource;
use App\Http\Resources\VideoSoundResource;
use App\Models\Video;
use App\Models\VideoSound;
use App\Support\CursorToken;
use Illuminate\Http\Request;

class VideoSoundController extends Controller
{
    use ApiHelpers;

    public function __construct()
    {
        $this->middleware('auth');
    }

    public function getSoundDetails(Request $request, $id)
    {
        $sound = VideoSound::active()->findOrFail($id);

        return $this->data(new VideoSoundResource($sound));
    }

    public function getSoundFeed(Request $request, $id)
    {
        $validated = $request->validate([
            'key' => 'required|string|min:32|max:32',
            'cursor' => 'sometimes',
        ]);

        $user = $request->user();
        $limit = 10;

        $key = $request->input('key');
        $preCursor = $validated['cursor'] ?? null;

        $sound = VideoSound::active()->findOrFail($id);
        $soundKey = substr($sound->fingerprint_hash, 0, 32);

        if (! hash_equals($soundKey, $key)) {
            return $this->error('Invalid sound entity');
        }

        $ctx = hash('sha256', implode('|', [
            $user->id,
            'explore-sounds',
            'sound:'.$sound->id,
            'limit:'.$limit,
            'order:id_desc',
        ]));

        $decodedCursor = null;
        $hops = 0;

        $maxPages = $user->is_admin ? 300 : 20;
        $maxItems = $user->is_admin ? 10000 : 400;

        if ($request->filled('cursor')) {
            [
                'cursor' => $decodedCursor,
                'hops' => $hops
            ] = CursorToken::decode($preCursor, $ctx);

            if ($hops >= $maxPages) {
                return $this->defaultTagResponse($request, $limit);
            }

            if (($hops * $limit) >= $maxItems) {
                return $this->defaultTagResponse($request, $limit);
            }
        }

        $pager = Video::published()
            ->where('sound_id', $sound->id)
            ->orderByDesc('id')
            ->cursorPaginate(
                perPage: $limit,
                columns: ['*'],
                cursorName: 'cursor',
                cursor: $decodedCursor
            );

        $nextLaravelCursor = $pager->nextCursor()?->encode();
        $nextCursorToken = CursorToken::encode($nextLaravelCursor, $ctx, $hops + 1);

        $tags = $pager->getCollection();

        return VideoCompactResource::collection($tags)->additional([
            'links' => [
                'first' => null,
                'last' => null,
                'prev' => null,
                'next' => null,
            ],
            'meta' => [
                'path' => $request->url(),
                'per_page' => $limit,
                'next_cursor' => $nextCursorToken,
                'prev_cursor' => $preCursor,
            ],
        ]);
    }

    public function defaultTagResponse($request, $limit)
    {
        return response()->json([
            'data' => [],
            'links' => [
                'first' => null,
                'last' => null,
                'prev' => null,
                'next' => null,
            ],
            'meta' => [
                'path' => $request->url(),
                'per_page' => $limit,
                'next_cursor' => null,
                'prev_cursor' => null,
            ],
        ]);
    }
}
