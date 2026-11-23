<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Traits\ApiHelpers;
use App\Http\Controllers\Controller;
use App\Models\Video;
use App\Services\ConfigService;
use App\Services\ForYouFeedService;
use App\Services\ImpressionBloomFilterService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ForYouFeedController extends Controller
{
    use ApiHelpers;

    public function __construct(
        private ForYouFeedService $feedService
    ) {
        $this->middleware('auth');
    }

    public function index(Request $request): JsonResponse
    {
        abort_unless(app(ConfigService::class)->forYouFeed(), 404);

        if ($request->user()->cannot('viewAny', [Video::class])) {
            return $this->error('Please finish setting up your account', 403);
        }

        $profile = $request->user()->profile;
        $cursor = $request->input('cursor');
        $limit = min((int) $request->input('limit', 20), 50);

        $feed = $this->feedService->getFeed($profile, $cursor, $limit);

        return response()->json($feed);
    }

    public function recordImpression(Request $request): JsonResponse
    {
        abort_unless(app(ConfigService::class)->forYouFeed(), 404);
        $validated = $request->validate([
            'video_id' => 'required|integer|exists:videos,id',
            'watch_duration' => 'required|integer|min:0',
            'completed' => 'boolean',
        ]);

        if ($request->user()->cannot('viewAny', [Video::class])) {
            return $this->error('Please finish setting up your account', 403);
        }

        $profile = $request->user()->profile;

        $video = Video::published()->find($request->input('video_id'));

        if (! $video) {
            return response()->json(['success' => true]);
        }

        if ($validated['completed']) {
            if (! app(ImpressionBloomFilterService::class)->mightContain($profile->id, $video->id)) {
                Video::published()->whereKey($validated['video_id'])->increment('views');
            }
        }

        $this->feedService->recordImpression(
            $profile->id,
            $validated['video_id'],
            $validated['watch_duration'],
            $validated['completed'] ?? false
        );

        return response()->json(['success' => true]);
    }

    public function recordFeedback(Request $request): JsonResponse
    {
        abort_unless(app(ConfigService::class)->forYouFeed(), 404);
        $validated = $request->validate([
            'video_id' => 'required|integer|exists:videos,id',
            'feedback_type' => [
                'required',
                'string',
                'in:like,dislike,not_interested,hide_creator',
            ],
        ]);

        if ($request->user()->cannot('viewAny', [Video::class])) {
            return $this->error('Please finish setting up your account', 403);
        }

        $profile = $request->user()->profile;

        $this->feedService->recordFeedback(
            $profile->id,
            $validated['video_id'],
            $validated['feedback_type']
        );

        return response()->json(['success' => true]);
    }

    public function removeFeedback(Request $request, int $videoId): JsonResponse
    {
        abort_unless(app(ConfigService::class)->forYouFeed(), 404);
        $profile = $request->user()->profile;

        $this->feedService->removeFeedback($profile->id, $videoId);

        return response()->json(['success' => true]);
    }
}
