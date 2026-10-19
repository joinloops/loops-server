<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Traits\ApiHelpers;
use App\Http\Controllers\Controller;
use App\Http\Resources\VideoHashtagResource;
use App\Models\VideoHashtag;
use App\Services\ExploreService;
use App\Services\UserFilterService;
use App\Support\CursorToken;
use Illuminate\Http\Request;

class ExploreController extends Controller
{
    use ApiHelpers;

    public function getTrendingTags(Request $request)
    {
        $tags = app(ExploreService::class)->getTrendingTags();

        return $this->data($request->user() ? $tags : array_slice($tags, 0, 8));
    }

    public function getTagFeed(Request $request, $id)
    {
        $validated = $request->validate([
            'cursor' => 'sometimes|string|max:2000',
            'limit' => 'sometimes|integer|min:1|max:20',
        ]);
        $tags = app(ExploreService::class)->getTrendingTags();
        $tagMap = collect($tags)->keyBy('name');
        abort_if(! $tagMap->get($id), 404);
        $hashtagId = $tagMap->get($id)['id'];

        $preCursor = $validated['cursor'] ?? null;
        $limit = $validated['limit'] ?? 10;

        if ($request->user()) {
            $user = $request->user();
        } elseif ($request->bearerToken()) {
            $user = $request->user('api');
        } else {
            $user = null;
        }

        if ($user) {
            $minLikes = config('loops.explore.tags.min_likes.user', 10);
            $tiers = $this->recencyTiers();

            $ctx = hash('sha256', implode('|', [
                $user->id,
                'explore-tags',
                'hashtag:'.$hashtagId,
                'limit:'.$limit,
                'order:id_desc',
                'minlikes:'.$minLikes,
                'tiers:'.$this->tierFingerprint(),
            ]));
            $decodedCursor = null;
            $hops = 0;

            $maxPages = $user->is_admin ? config('loops.explore.tags.pagination.admin.max_pages', 100) : config('loops.explore.tags.pagination.user.max_pages', 10);
            $maxItems = $user->is_admin ? config('loops.explore.tags.pagination.admin.max_items', 300) : config('loops.explore.tags.pagination.user.max_items', 120);

            if ($request->filled('cursor')) {
                ['cursor' => $decodedCursor, 'hops' => $hops] = CursorToken::decode($request->input('cursor'), $ctx);

                if ($hops >= $maxPages) {
                    return $this->defaultTagResponse($request, $limit);
                }

                if (($hops * $limit) >= $maxItems) {
                    return $this->defaultTagResponse($request, $limit);
                }
            }

            $blockedIds = UserFilterService::getAll($user->profile_id);

            $pager = VideoHashtag::where('video_hashtags.hashtag_id', $hashtagId)
                ->where('video_hashtags.visibility', 1)
                ->join('videos', 'videos.id', '=', 'video_hashtags.video_id')
                ->join('profiles', 'profiles.id', '=', 'videos.profile_id')
                ->where('videos.status', 2)
                ->where('profiles.status', 1)
                ->where(function ($q) use ($minLikes, $tiers) {
                    $q->where('videos.likes', '>', $minLikes);

                    foreach ($tiers as $tier) {
                        $q->orWhere(function ($inner) use ($tier) {
                            $inner->where('videos.created_at', '>=', $tier['after'])
                                ->where('videos.likes', '>=', $tier['min_likes']);
                        });
                    }
                })
                ->when(! empty($blockedIds), fn ($q) => $q->whereNotIn('videos.profile_id', $blockedIds))
                ->select('video_hashtags.*')
                ->orderByDesc('video_hashtags.id')
                ->cursorPaginate(
                    perPage: $limit,
                    columns: ['*'],
                    cursorName: 'cursor',
                    cursor: $decodedCursor
                );

            $nextLaravelCursor = $pager->nextCursor()?->encode();
            $nextCursorToken = CursorToken::encode($nextLaravelCursor, $ctx, $hops + 1);

            $tags = $pager->getCollection();

            return VideoHashtagResource::collection($tags)->additional([
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
        } else {
            abort_if($request->has('cursor'), 404);
            $feed = app(ExploreService::class)->getGuestTagFeed($hashtagId);
        }

        return VideoHashtagResource::collection($feed);
    }

    protected function recencyTiers(): array
    {
        return collect(config('loops.explore.tags.recency_tiers', []))
            ->map(fn ($tier) => [
                'after' => now()->subHours((int) $tier['hours']),
                'min_likes' => (int) $tier['min_likes'],
            ])
            ->sortBy('min_likes')
            ->values()
            ->all();
    }

    protected function tierFingerprint(): string
    {
        return substr(hash('sha256', json_encode(config('loops.explore.tags.recency_tiers', []))), 0, 12);
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
