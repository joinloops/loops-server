<?php

namespace App\Http\Controllers;

use App\Federation\ActivityBuilders\CreateActivityBuilder;
use App\Federation\ActivityBuilders\StarterKitActivityBuilder;
use App\Models\Comment;
use App\Models\CommentReply;
use App\Models\Profile;
use App\Models\QuoteAuthorization;
use App\Models\StarterKit;
use App\Models\StarterKitAccount;
use App\Models\Video;
use App\Services\ActivityPubCacheService;
use App\Services\ConfigService;
use App\Services\HashidService;
use App\Services\VideoService;
use Illuminate\Http\Request;

class ObjectController extends Controller
{
    protected $apCache;

    public function __construct(ActivityPubCacheService $apCache)
    {
        $this->apCache = $apCache;
    }

    public function showProfile(Request $request, $username)
    {
        $config = app(ConfigService::class);

        if ($request->wantsJson()) {
            if (! $config->federation()) {
                return abort(404, 'Not found');
            }

            $profile = Profile::active()->whereUsername($username)->whereLocal(true)->first();

            if (! $profile) {
                return $this->activityPubError('Record not found', 404);
            }

            if ($profile->status != 1) {
                return $this->activityPubError('Record not available', 400);
            }

            $cacheKey = $this->apCache->profileKey($profile->id);
            $activityPub = $this->apCache->getOrCache($cacheKey, function () use ($profile) {
                return $profile->toActivityPub();
            });

            return $this->activityPubResponseWithCache($activityPub);
        }

        $profile = Profile::whereUsername($username)->first();

        if (! $profile) {
            abort(404, 'Profile not found');
        }

        if ($profile->status != 1) {
            return view('welcome');
        }

        return view('profile', compact('profile'));
    }

    public function showVideo(Request $request, $hashId)
    {
        $id = HashidService::safeDecode($hashId);

        $video = Video::with('profile')->whereStatus(2)->findOrFail($id);

        if ($request->wantsJson() && $video->is_local) {
            abort_if($video->profile->status != 1, 403, 'Resource is not available');

            $config = app(ConfigService::class);

            if (! $config->federation()) {
                return abort(404, 'Not found');
            }

            if ($video->visibility !== 1) {
                return $this->activityPubError('You do not have permission to access this resource.', 403);
            }

            if ($request->filled('cid')) {
                $hashId = $request->input('cid');
                $cid = HashidService::safeDecode($hashId);
                $vid = $id;
                $videoHashId = $hashId;

                return $this->renderVideoCommentObject($video, $videoHashId, $hashId, $vid, $cid);
            } elseif ($request->filled('rid')) {
                $replyHashId = $request->input('rid');
                $cid = HashidService::safeDecode($replyHashId);
                $vid = $id;
                $videoHashId = $hashId;
                $comment = CommentReply::with('parent')->where('visibility', 1)->whereNull('ap_id')->where('status', 'active')->where('video_id', $vid)->findOrFail($cid);

                return $this->renderVideoCommentReplyObject($video, $comment, $videoHashId, $hashId, $vid, $cid);
            } else {
                return $this->renderVideoObject($video, $hashId);
            }
        }

        if ($video->profile->status != 1) {
            return view('welcome');
        }

        $videoData = VideoService::getMediaData($id);

        return view('video', compact('videoData'));
    }

    protected function renderVideoObject($video, $hashId)
    {
        $cacheKey = $this->apCache->videoKey($video->id);

        $res = $this->apCache->getOrCache($cacheKey, function () use ($video) {
            return CreateActivityBuilder::buildForVideoFlat($video->profile, $video);
        });

        return $this->activityPubResponseWithCache($res);
    }

    public function showVideoObject(Request $request, $actor, $id)
    {
        $profile = Profile::active()->findOrFail($actor);
        $video = Video::whereStatus(2)->findOrFail($id);
        abort_if($actor != $video->profile_id || ! $video->is_local, 404);
        $hashId = HashidService::safeEncode($video->id);

        if ($request->wantsJson()) {
            return $this->renderVideoObject($video, $hashId);
        }

        return redirect('/v/'.$hashId);
    }

    public function showComment(Request $request, $videoHashId, $hashId)
    {
        $vid = HashidService::safeDecode($videoHashId);
        $cid = HashidService::safeDecode($hashId);

        $video = Video::whereStatus(2)->findOrFail($vid);

        if ($request->wantsJson() && $video->is_local) {
            if ($video->visibility !== 1) {
                return $this->activityPubError('You do not have permission to access this resource.', 403);
            }

            return $this->renderVideoCommentObject($video, $videoHashId, $hashId, $vid, $cid);
        }

        return redirect('/v/'.$videoHashId.'?c='.$hashId);
    }

    public function showCommentObject(Request $request, $actor, $id)
    {
        $comment = Comment::with('profile')->where('visibility', 1)->whereNull('ap_id')->where('status', 'active')->findOrFail($id);
        abort_if($comment->profile->status != 1, 403, 'Resource not available');
        $video = Video::whereStatus(2)->findOrFail($comment->video_id);
        $videoHashId = HashidService::safeEncode($video->id);
        $hashId = HashidService::safeEncode($comment->id);
        $vid = $video->id;
        $cid = $comment->id;

        return $this->renderVideoCommentObject($video, $videoHashId, $hashId, $vid, $cid);
    }

    public function showReplyObject(Request $request, $actor, $id)
    {
        $comment = CommentReply::with(['parent', 'profile'])->where('visibility', 1)->whereNull('ap_id')->where('status', 'active')->findOrFail($id);
        abort_if($comment->profile->status != 1, 403, 'Resource not available');
        $video = Video::whereStatus(2)->findOrFail($comment->video_id);
        $videoHashId = HashidService::safeEncode($video->id);
        $hashId = HashidService::safeEncode($comment->id);
        $vid = $video->id;
        $cid = $comment->id;

        return $this->renderVideoCommentReplyObject($video, $comment, $videoHashId, $hashId, $vid, $cid);
    }

    protected function renderVideoCommentObject($video, $videoHashId, $hashId, $vid, $cid)
    {
        $comment = Comment::with('profile')->where('visibility', 1)->whereNull('ap_id')->where('status', 'active')->whereVideoId($vid)->findOrFail($cid);
        abort_if($comment->profile->status != 1, 403, 'Resource not available');

        $cacheKey = $this->apCache->commentKey($comment->id);

        $res = $this->apCache->getOrCache($cacheKey, function () use ($comment) {
            return CreateActivityBuilder::buildForCommentFlat($comment->profile, $comment);
        });

        return $this->activityPubResponseWithCache($res);
    }

    protected function renderVideoCommentReplyObject($video, $comment, $videoHashId, $hashId, $vid, $cid)
    {
        abort_if($comment->profile->status != 1, 403, 'Resource not available');

        $cacheKey = $this->apCache->replyKey($comment->id);

        $res = $this->apCache->getOrCache($cacheKey, function () use ($comment) {
            return CreateActivityBuilder::buildForCommentReplyFlat($comment->profile, $comment);
        });

        return $this->activityPubResponseWithCache($res);
    }

    public function getQuoteAuthorization($profileId, $authId)
    {
        $authorization = QuoteAuthorization::where('id', $authId)
            ->where('quoted_profile_id', $profileId)
            ->firstOrFail();

        $cacheKey = "quote_auth:{$authId}";

        $activityPub = $this->apCache->getOrCache($cacheKey, function () use ($authorization) {
            return $authorization->toActivityPub();
        }, 900);

        return $this->activityPubResponseWithCache($activityPub);
    }

    public function showVideoObjectLikes(Request $request, $actor, $id)
    {
        $config = app(ConfigService::class);

        if (! $config->federation()) {
            return abort(404, 'Not found');
        }

        $videoMetadata = VideoService::getMediaData($id);

        if (! $videoMetadata) {
            return response()->json(['error' => 'Resource does not exist'], 404);
        }

        $acctId = data_get($videoMetadata, 'account.id', null);

        if (! hash_equals($acctId, $actor)) {
            return response()->json(['error' => 'Resource does not exist'], 404);
        }

        $cacheKey = "video_object_likes:v1:{$id}";

        $activityPub = $this->apCache->getOrCache($cacheKey, function () use ($actor, $id) {
            $video = Video::with('profile')->whereProfileId($actor)->whereStatus(2)->find($id);

            if ($video && $video->is_local && $video->visibility === 1) {
                abort_if($video->profile->status != 1, 403, 'Resource is not available');

                // @phpstan-ignore-next-line
                if ($video->visibility !== 1) {
                    return $this->activityPubError('You do not have permission to access this resource.', 403);
                }

                return [
                    '@context' => 'https://www.w3.org/ns/activitystreams',
                    'id' => $video->permalink('/likes'),
                    'type' => 'Collection',
                    'totalItems' => (int) $video->likes,
                ];

            } else {
                return ['error' => 'Resource does not exist'];
            }
        }, 900);

        return $this->activityPubResponseWithCache($activityPub);
    }

    public function showVideoObjectShares(Request $request, $actor, $id)
    {
        $config = app(ConfigService::class);

        if (! $config->federation()) {
            return abort(404, 'Not found');
        }

        $videoMetadata = VideoService::getMediaData($id);

        if (! $videoMetadata) {
            return response()->json(['error' => 'Resource does not exist'], 404);
        }

        $acctId = data_get($videoMetadata, 'account.id', null);

        if (! hash_equals($acctId, $actor)) {
            return response()->json(['error' => 'Resource does not exist'], 404);
        }

        $cacheKey = "video_object_shares:v1:{$id}";

        $activityPub = $this->apCache->getOrCache($cacheKey, function () use ($actor, $id) {
            $video = Video::with('profile')->whereProfileId($actor)->whereStatus(2)->find($id);

            if ($video && $video->is_local && $video->visibility === 1) {
                abort_if($video->profile->status != 1, 403, 'Resource is not available');

                // @phpstan-ignore-next-line
                if ($video->visibility !== 1) {
                    return $this->activityPubError('You do not have permission to access this resource.', 403);
                }

                return [
                    '@context' => 'https://www.w3.org/ns/activitystreams',
                    'id' => $video->permalink('/shares'),
                    'type' => 'Collection',
                    'totalItems' => (int) $video->shares,
                ];

            } else {
                return ['error' => 'Resource does not exist'];
            }
        }, 900);

        return $this->activityPubResponseWithCache($activityPub);
    }

    public function showStarterKitRedirect(Request $request, $hid, $slug)
    {
        abort_unless(app(ConfigService::class)->starterKits(), 404);

        $hashId = HashidService::safeDecode($hid);
        $kit = StarterKit::active()->findOrFail($hashId);

        if ($request->wantsJson()) {
            $cacheKey = "starter_kit_obj:v1:{$kit->id}";

            $activityPub = $this->apCache->getOrCache($cacheKey, function () use ($kit) {
                return StarterKitActivityBuilder::buildKit($kit);
            }, 900);

            return $this->activityPubResponseWithCache($activityPub);
        }

        return view('welcome');
    }

    public function showStarterKit(Request $request, $id)
    {
        abort_unless(app(ConfigService::class)->starterKits(), 404);

        $kit = StarterKit::active()->findOrFail($id);

        if ($request->wantsJson()) {
            return $this->activityPubResponseWithCache(StarterKitActivityBuilder::buildKit($kit));
        }

        return view('welcome');
    }

    public function showStarterKitAccountItem(Request $request, $kit, $pid)
    {
        abort_unless(app(ConfigService::class)->starterKits(), 404);

        $kit = StarterKit::active()->findOrFail($kit);
        $account = StarterKitAccount::approved()->whereStarterKitId($kit->id)->whereProfileId($pid)->firstOrFail();

        return StarterKitActivityBuilder::buildAccountItem($account);
    }

    public function showStarterKitAccountAttestation(Request $request, $kit, $id)
    {
        abort_unless(app(ConfigService::class)->starterKits(), 404);

        $kit = StarterKit::active()->findOrFail($kit);
        $account = StarterKitAccount::approved()->whereStarterKitId($kit->id)->findOrFail($id);

        return StarterKitActivityBuilder::buildAccountAttestation($account, $kit);
    }

    /**
     * Return ActivityPub response with proper cache headers
     */
    protected function activityPubResponseWithCache($data)
    {
        $json = is_array($data) ? json_encode($data, JSON_UNESCAPED_SLASHES) : $data;
        $etag = $this->apCache->getETag($json);

        return response($json)
            ->header('Content-Type', 'application/activity+json; charset=utf-8')
            ->header('Cache-Control', 'public, max-age=3600, s-maxage=7200, stale-while-revalidate=86400')
            ->header('ETag', $etag)
            ->header('Vary', 'Accept');
    }
}
