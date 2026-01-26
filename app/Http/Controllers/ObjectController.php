<?php

namespace App\Http\Controllers;

use App\Federation\ActivityBuilders\CreateActivityBuilder;
use App\Models\Comment;
use App\Models\CommentReply;
use App\Models\Profile;
use App\Models\QuoteAuthorization;
use App\Models\Video;
use App\Services\ConfigService;
use App\Services\HashidService;
use App\Services\VideoService;
use Illuminate\Http\Request;

class ObjectController extends Controller
{
    public function showProfile(Request $request, $username)
    {
        $config = app(ConfigService::class);

        if ($request->wantsJson()) {
            if (! $config->federation()) {
                return abort(404, 'Not found');
            }

            if ($config->federationAuthorizedFetch()) {
                return abort(403, 'You do not have permission to view this.');
            }

            $profile = Profile::whereUsername($username)->whereLocal(true)->first();

            if (! $profile) {
                return $this->activityPubError('Record not found', 404);
            }

            if ($profile->status != 1) {
                return $this->activityPubError('Record not available', 400);
            }

            return $this->activityPubResponse($profile->toActivityPub());
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

            if ($config->federationAuthorizedFetch()) {
                return abort(403, 'You do not have permission to view this.');
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
        $res = CreateActivityBuilder::buildForVideoFlat($video->profile, $video);

        return $this->activityPubResponse($res);
    }

    public function showVideoObject(Request $request, $actor, $id)
    {
        $profile = Profile::active()->findOrFail($actor);
        $video = Video::whereStatus(2)->findOrFail($id);
        abort_if($actor != $video->profile_id || ! $video->is_local, 404);
        $hashId = HashidService::safeEncode($video->id);

        return $this->renderVideoObject($video, $hashId);
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
        $res = CreateActivityBuilder::buildForCommentFlat($comment->profile, $comment);

        return $this->activityPubResponse($res);
    }

    protected function renderVideoCommentReplyObject($video, $comment, $videoHashId, $hashId, $vid, $cid)
    {
        abort_if($comment->profile->status != 1, 403, 'Resource not available');

        $res = CreateActivityBuilder::buildForCommentReplyFlat($comment->profile, $comment);

        return $this->activityPubResponse($res);
    }

    public function getQuoteAuthorization($profileId, $authId)
    {
        $authorization = QuoteAuthorization::where('id', $authId)
            ->where('quoted_profile_id', $profileId)
            ->firstOrFail();

        return response()->json($authorization->toActivityPub())
            ->header('Content-Type', 'application/activity+json; charset=utf-8')
            ->header('Cache-Control', 'public, max-age=300');
    }
}
