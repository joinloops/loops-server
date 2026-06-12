<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Traits\ApiHelpers;
use App\Http\Controllers\Controller;
use App\Http\Resources\VideoResource;
use App\Models\Video;
use App\Services\FeedService;
use App\Services\FollowerService;
use App\Services\UserActivityService;
use Illuminate\Http\Request;

class FeedController extends Controller
{
    use ApiHelpers;

    public function __construct()
    {
        $this->middleware('auth:web,api');
    }

    public function selfAccountFeed(Request $request)
    {
        if ($request->user()->cannot('viewAny', [Video::class])) {
            return $this->error('Please finish setting up your account', 403);
        }

        $validated = $request->validate([
            'sort' => 'sometimes|in:Latest,Popular,Oldest',
            'limit' => 'sometimes|integer|min:1|max:20',
        ]);

        $limit = data_get($validated, 'limit', 10);
        $sort = data_get($validated, 'sort', 'Latest');
        $showPinned = $sort === 'Latest';
        app(UserActivityService::class)->markActive($request->user());

        return FeedService::getAccountFeed($request->user()->profile_id, $limit, $sort, $showPinned);
    }

    public function getForYouFeed(Request $request)
    {
        $user = $request->user();
        if ($user) {
            if ($user->cannot('viewAny', [Video::class])) {
                return $this->error('Please finish setting up your account', 403);
            }
            app(UserActivityService::class)->markActive($user);
            FeedService::enforcePaginationLimit($request);
            $hideAi = $user->hide_ai;
            $feed = FeedService::getVideoFeed($user->profile_id, 5, $hideAi);
        } else {
            // Guest: return public trending feed
            FeedService::enforcePaginationLimit($request);
            $feed = Video::published()
                ->where('videos.visibility', 1)
                ->orderBy('videos.created_at', 'desc')
                ->cursorPaginate(5)
                ->withQueryString();
        }

        return VideoResource::collection($feed);
    }

    public function getFollowingFeed(Request $request)
    {
        $user = $request->user();
        if ($user->cannot('viewAny', [Video::class])) {
            return $this->error('Please finish setting up your account', 403);
        }

        app(UserActivityService::class)->markActive($user);

        FeedService::enforceFollowingPaginationLimit($request);

        $me = $user->profile_id;
        $hideAi = $user->hide_ai;

        $feed = Video::query()
            ->published()
            ->whereIn('visibility', [1, 2, 3, 4])
            ->where(function ($q) use ($me) {
                $q->where('videos.profile_id', $me)
                    ->orWhereExists(function ($sub) use ($me) {
                        $sub->selectRaw(1)
                            ->from('followers')
                            ->whereColumn('followers.following_id', 'videos.profile_id')
                            ->where('followers.profile_id', $me);
                    });
            })
            ->when($hideAi, function ($query, $hideAi) {
                $query->where('contains_ai', false);
            })
            ->orderBy('videos.id', 'desc')
            ->cursorPaginate(5)
            ->withQueryString();

        return VideoResource::collection($feed);
    }

    public function getAccountFeedWithCursor(Request $request, $profileId)
    {
        $request->validate([
            'id' => 'required|integer|min:1',
            'limit' => 'sometimes|integer|min:1|max:20',
        ]);

        $videoId = $request->input('id');
        $limit = $request->input('limit', 10);

        $user = $request->user();
        $authProfileId = $user->profile_id;

        $video = Video::where('profile_id', $profileId)
            ->where('id', $videoId)
            ->published()
            ->firstOrFail();

        if ($user && $request->user()->cannot('view', $video->profile)) {
            return $this->error('Cannot access this profile', 403);
        }

        $isSelf = (int) $authProfileId === (int) $profileId;
        $visibilityScopes = [1];

        if ($isSelf) {
            $visibilityScopes = [1, 2, 3, 4, 5];
        } else {
            $follows = FollowerService::follows($authProfileId, $video->profile_id);
            $visibilityScopes = $follows ? [1, 2, 3, 4] : [1, 2, 3];
        }

        $feed = Video::select('videos.*')
            ->whereIn('visibility', $visibilityScopes)
            ->selectRaw('CASE WHEN video_bookmarks.id IS NOT NULL THEN 1 ELSE 0 END as is_bookmarked')
            ->leftJoin('video_bookmarks', function ($join) use ($authProfileId) {
                $join->on('video_bookmarks.video_id', '=', 'videos.id')
                    ->where('video_bookmarks.profile_id', '=', $authProfileId);
            })
            ->where('videos.profile_id', $profileId)
            ->published()
            ->where('videos.id', '<=', $videoId)
            ->orderByDesc('videos.id')
            ->cursorPaginate($limit)
            ->withQueryString();

        return VideoResource::collection($feed);
    }
}
