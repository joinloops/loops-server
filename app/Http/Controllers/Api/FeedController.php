<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Traits\ApiHelpers;
use App\Http\Controllers\Controller;
use App\Http\Resources\VideoResource;
use App\Models\Video;
use App\Services\FeedService;
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

        return FeedService::getAccountFeed($request->user()->profile_id);
    }

    public function getForYouFeed(Request $request)
    {
        if ($request->user()->cannot('viewAny', [Video::class])) {
            return $this->error('Please finish setting up your account', 403);
        }
        app(UserActivityService::class)->markActive($request->user());
        FeedService::enforcePaginationLimit($request);
        $feed = FeedService::getVideoFeed($request->user()->profile_id, 5);

        return VideoResource::collection($feed);
    }

    public function getFollowingFeed(Request $request)
    {
        if ($request->user()->cannot('viewAny', [Video::class])) {
            return $this->error('Please finish setting up your account', 403);
        }

        FeedService::enforceFollowingPaginationLimit($request);

        $me = $request->user()->profile_id;

        $feed = Video::query()
            ->published()
            ->where(function ($q) use ($me) {
                $q->where('videos.profile_id', $me)
                    ->orWhereExists(function ($sub) use ($me) {
                        $sub->selectRaw(1)
                            ->from('followers')
                            ->whereColumn('followers.following_id', 'videos.profile_id')
                            ->where('followers.profile_id', $me);
                    });
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

        $video = Video::where('profile_id', $profileId)
            ->where('id', $videoId)
            ->published()
            ->firstOrFail();

        if ($request->user() && $request->user()->cannot('view', $video->profile)) {
            return $this->error('Cannot access this profile', 403);
        }

        $feed = Video::whereProfileId($profileId)
            ->published()
            ->where('id', '<=', $videoId)
            ->orderByDesc('id')
            ->cursorPaginate($limit)
            ->withQueryString();

        return VideoResource::collection($feed);
    }
}
