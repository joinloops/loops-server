<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Traits\ApiHelpers;
use App\Http\Controllers\Controller;
use App\Http\Resources\CommentReplyResource;
use App\Http\Resources\CommentResource;
use App\Http\Resources\FollowerResource;
use App\Http\Resources\FollowingResource;
use App\Http\Resources\ProfileResource;
use App\Http\Resources\VideoHashtagResource;
use App\Http\Resources\VideoResource;
use App\Models\Comment;
use App\Models\CommentReply;
use App\Models\Follower;
use App\Models\Hashtag;
use App\Models\Page;
use App\Models\Profile;
use App\Models\SystemMessage;
use App\Models\Video;
use App\Models\VideoBookmark;
use App\Models\VideoHashtag;
use App\Services\AccountService;
use App\Services\FeedService;
use App\Services\FrontendService;
use App\Services\IntlService;
use App\Services\ReportService;
use App\Services\SystemMessageService;
use App\Support\CursorToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class WebPublicController extends Controller
{
    use ApiHelpers;

    public function getFeed(Request $request)
    {
        $res = Cache::remember('wpc:get-feed', now()->addMinutes(45), function () {
            $request = new Request;
            $feed = FeedService::getPublicVideoFeed(20);

            $feed = collect($feed)->shuffle();

            return VideoResource::collection($feed->all())->toArray($request);
        });

        return response()->json([
            'data' => $res,
            'links' => [
                'first' => null,
                'last' => null,
                'prev' => null,
                'next' => null,
            ],
            'meta' => [
                'path' => $request->url(),
                'per_page' => count($res),
                'next_cursor' => null,
                'prev_cursor' => null,
            ],
        ]);
    }

    public function showVideo(Request $request, $id)
    {
        $video = Video::with('profile')->published()->find($id);

        abort_if($video->profile->status != 1, 400, 'Resource not available');

        if (! $video || ($request->user() && $request->user()->cannot('view', $video))) {
            return $this->error('Video not found or is unavailable', 404);
        }

        if ($request->user()) {
            // @phpstan-ignore-next-line
            $video->is_bookmarked = VideoBookmark::whereProfileId($request->user()->profile_id)->whereVideoId($video->id)->exists();
        }

        return new VideoResource($video);
    }

    /**
     * Get video comments.
     */
    public function comments(Request $request, $id)
    {
        $video = Video::with('profile')->published()->canComment()->find($id);

        abort_if($video->profile->status != 1, 400, 'Resource not available');

        if (! $video || ($request->user() && $request->user()->cannot('view', [Video::class, $video]))) {
            return $this->error('Video not found or is unavailable or has comments disabled', 404);
        }

        $comments = Comment::withTrashed()
            ->whereVideoId($video->id)
            ->where('is_hidden', false)
            ->orderByDesc('id')
            ->cursorPaginate(10);

        if ($video->has_hidden_comments) {
            return CommentResource::collection($comments)->additional(['meta' => ['has_hidden_comments' => true]]);
        }

        return CommentResource::collection($comments);
    }

    public function getCommentById(Request $request, $videoId, $commentId)
    {
        $video = Video::with('profile')->published()->canComment()->find($videoId);

        abort_if($video->profile->status != 1, 400, 'Resource not available');

        $comment = Comment::withTrashed()
            ->whereVideoId($video->id)
            ->where('is_hidden', false)
            ->findOrFail($commentId);

        return new CommentResource($comment);
    }

    public function getReplyById(Request $request, $videoId, $replyId)
    {
        $video = Video::with('profile')->published()->canComment()->find($videoId);

        if (! $video) {
            return response()->json([
                'error' => 'Video not found or comments are disabled',
            ], 404);
        }

        abort_if($video->profile->status != 1, 400, 'Resource not available');

        $reply = CommentReply::withTrashed()
            ->with(['profile', 'parent'])
            ->whereVideoId($video->id)
            ->findOrFail($replyId);

        $parentComment = $reply->parent;

        if (! $parentComment) {
            return response()->json([
                'error' => 'Parent comment not found',
            ], 404);
        }

        return response()->json([
            'data' => new CommentReplyResource($reply),
            'meta' => [
                'type' => 'reply',
                'video_id' => $video->id,
                'parent_comment' => new CommentResource($parentComment),
            ],
        ]);
    }

    /**
     * Get video comment replies.
     */
    public function commentsThread(Request $request, $id)
    {
        $this->validate($request, [
            'cr' => 'required|integer',
        ]);

        $video = Video::with('profile')->published()->canComment()->find($id);

        abort_if($video->profile->status != 1, 400, 'Resource not available');

        if (! $video || ($request->user() && $request->user()->cannot('view', [Video::class, $video]))) {
            return $this->error('Video not found or is unavailable or has comments disabled', 404);
        }

        $comments = CommentReply::withTrashed()
            ->whereVideoId($video->id)
            ->whereCommentId($request->input('cr'))
            ->where('is_hidden', false)
            ->orderByDesc('id')
            ->cursorPaginate(3)
            ->withQueryString();

        return CommentReplyResource::collection($comments);
    }

    public function reportTypes()
    {
        return response()->json(ReportService::getRules());
    }

    public function getAccountInfoByUsername(Request $request, $id)
    {
        if ($request->user() && $request->user()->cannot('viewAny', [Video::class])) {
            return $this->error('Please finish setting up your account', 403);
        }

        $profile = Profile::whereUsername($id)->firstOrFail();

        if ($profile->status != 1) {
            return $this->error('This resource is not available', 403);
        }

        $res = (new ProfileResource($profile))->toArray($request);
        $res['is_owner'] = $request->user()?->profile_id == $profile->id;
        $res['likes_count'] = AccountService::getAccountLikesCount($profile->id);

        return response()->json(['data' => $res], 200, [], JSON_UNESCAPED_SLASHES);
    }

    public function accountFollowers(Request $request, $id)
    {
        $profile = Profile::findOrFail($id);

        if ($profile->status != 1) {
            return $this->error('This resource is not available', 403);
        }

        if ($request->user() && $request->user()->cannot('viewAny', [Profile::class])) {
            return $this->error('Unavailable', 403);
        }

        $followers = Follower::whereFollowingId($id)->orderBy('id')->limit(15)->get();

        return FollowerResource::collection($followers);
    }

    public function accountFollowing(Request $request, $id)
    {
        $profile = Profile::findOrFail($id);

        if ($profile->status != 1) {
            return $this->error('This resource is not available', 403);
        }

        if ($request->user() && $request->user()->cannot('viewAny', [Profile::class])) {
            return $this->error('Unavailable', 403);
        }

        $followers = Follower::whereProfileId($id)->orderBy('id')->limit(15)->get();

        return FollowingResource::collection($followers);
    }

    public function getAccountFeed(Request $request, $id)
    {
        if ($request->user() && $request->user()->cannot('viewAny', [Video::class])) {
            return $this->error('Please finish setting up your account', 403);
        }

        $request->validate([
            'sort' => 'sometimes|string|in:Latest,Popular,Oldest',
        ]);

        $sort = $request->input('sort', 'Latest');
        $showPinned = $sort === 'Latest';

        $profile = Profile::findOrFail($id);

        if ($profile->status != 1) {
            return $this->error('This resource is not available', 400);
        }

        return FeedService::getAccountFeed($id, 10, $sort, $showPinned);
    }

    public function getContactInfo()
    {
        $keys = ['general.adminEmail', 'general.supportEmail', 'general.supportForum', 'general.supportFediverseAccount'];

        $keys = Cache::get('settings:admin');
        $contactInfo = [
            'admin_email' => $keys['general.adminEmail'],
            'support_email' => $keys['general.supportEmail'],
            'support_forum_url' => $keys['general.supportForum'],
            'fediverse_account' => $keys['general.supportFediverseAccount'],
        ];

        $contactInfo = array_filter($contactInfo, function ($value) {
            return ! is_null($value) && $value !== '';
        });

        return response()->json($contactInfo);
    }

    public function getPageContent(Request $request)
    {
        $this->validate($request, [
            'slug' => 'required|string|min:1|max:100',
        ]);

        $page = Page::whereSlug($request->slug)->published()->firstOrFail();
        $content = Str::of($page->content)->markdown();
        $res = [
            'title' => $page->title,
            'content' => $content,
            'slug' => $page->slug,
            'created_at' => $page->created_at,
            'updated_at' => $page->updated_at,
        ];

        return $this->data($res);
    }

    public function getVideoTags(Request $request, $id)
    {
        $tag = Hashtag::where('name', $id)->firstOrFail();
        abort_if((bool) $tag->is_banned, 404);

        $guest = $request->user() === null;

        if ($guest && $tag->is_nsfw) {
            return $this->defaultCollection(['is_nsfw' => true, 'is_guest' => true]);
        }

        if ($guest) {
            $tags = VideoHashtag::whereHashtagId($tag->id)->orderByDesc('id')->limit(30)->get();

            return VideoHashtagResource::collection($tags)->additional([
                'meta' => [
                    'total_results' => $tag->count,
                    'limit' => 30,
                    'next_cursor' => null,
                    'has_more' => false,
                    'is_guest' => true,
                ],
            ]);
        }

        $validated = $request->validate([
            'cursor' => 'sometimes|string|max:2000',
            'limit' => 'sometimes|integer|min:1|max:30',
        ]);

        $preCursor = $validated['cursor'] ?? null;
        $limit = $validated['limit'] ?? 10;

        $maxPages = $request->user()->is_admin ? 100 : 5;
        $maxItems = $request->user()->is_admin ? 1000 : 80;

        $ctx = hash('sha256', implode('|', [
            $request->user()->id,
            'video-tags',
            'hashtag:'.$tag->id,
            'limit:'.$limit,
            'order:id_desc',
        ]));

        $decodedCursor = null;
        $hops = 0;

        if ($request->filled('cursor')) {
            ['cursor' => $decodedCursor, 'hops' => $hops] = CursorToken::decode($request->input('cursor'), $ctx);

            if ($hops >= $maxPages) {
                return $this->defaultTagResponse($request, $limit, $tag->count);
            }

            if (($hops * $limit) >= $maxItems) {
                return $this->defaultTagResponse($request, $limit, $tag->count);
            }
        }

        $pager = VideoHashtag::whereHashtagId($tag->id)
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
                'total_results' => $tag->count,
            ],
        ]);
    }

    public function getPublicSystemNotification(Request $request, $id)
    {
        abort_unless($request->hasHeader('X-LOOPS-APP'), 404);
        $systemMessage = SystemMessage::active()->published()->where('key_id', $id)->firstOrFail();
        $cached = app(SystemMessageService::class)->getFull($systemMessage->id);

        return response()->json(['data' => $cached]);
    }

    public function defaultTagResponse($request, $limit, $totalCount = 0)
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
                'total_results' => $totalCount,
                'path' => $request->url(),
                'per_page' => $limit,
                'next_cursor' => null,
                'prev_cursor' => null,
            ],
        ]);
    }

    public function authStartFallback(Request $request)
    {
        $res = [
            'errors' => 'This app is outdated. Get the new version: loops.video/app-update',
        ];

        return response()->json($res);
    }

    public function getLanguagesList(Request $request)
    {
        return $this->data(app(IntlService::class)->get());
    }

    public function appConfiguration()
    {
        $config = FrontendService::getCache();
        $config['app']['software'] = 'loops';
        $config['app']['version'] = app('app_version');
        unset($config['branding']);

        return response()->json($config);
    }

    private function defaultCollection($meta = [])
    {
        return [
            'data' => [],
            'links' => [],
            'meta' => $meta,
        ];
    }
}
