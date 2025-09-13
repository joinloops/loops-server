<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Traits\ApiHelpers;
use App\Http\Controllers\Controller;
use App\Http\Requests\DeleteVideoRequest;
use App\Http\Requests\GetMentionAutocomplete;
use App\Http\Requests\StoreCommentRequest;
use App\Http\Requests\StoreVideoRequest;
use App\Http\Requests\UpdateVideoRequest;
use App\Http\Resources\CommentReplyResource;
use App\Http\Resources\CommentResource;
use App\Http\Resources\HashtagResource;
use App\Http\Resources\ProfileResource;
use App\Http\Resources\VideoResource;
use App\Jobs\Video\VideoOptimizeJob;
use App\Jobs\Video\VideoThumbnailJob;
use App\Models\Comment;
use App\Models\CommentLike;
use App\Models\CommentReply;
use App\Models\CommentReplyLike;
use App\Models\Hashtag;
use App\Models\Notification;
use App\Models\Profile;
use App\Models\Video;
use App\Models\VideoLike;
use App\Services\AccountService;
use App\Services\LikeService;
use App\Services\NotificationService;
use App\Services\UserActivityService;
use App\Services\VideoService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Stevebauman\Purify\Facades\Purify;

class VideoController extends Controller
{
    use ApiHelpers;

    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreVideoRequest $request)
    {
        $pid = $request->user()->profile_id;
        app(UserActivityService::class)->markActive($request->user());
        $profile = Profile::findOrFail($pid);
        $videoFile = $request->file('video');
        $videoMeta = [
            'size' => ceil($videoFile->getSize() / 1024),
            'name' => $videoFile->getClientOriginalName(),
            'mime' => $videoFile->getMimeType(),
        ];

        $model = new Video;
        $model->profile_id = $pid;
        $model->caption = Purify::clean($request->description);
        $model->size_kb = $videoMeta['size'];
        $model->is_sensitive = $request->filled('is_sensitive') ? (bool) $request->boolean('is_sensitive') : false;
        $model->comment_state = $request->filled('comment_state') ? ($request->input('comment_state') == 4 ? 4 : 0) : 4;
        $model->can_download = $request->filled('can_download') ? $request->boolean('can_download') : false;
        $model->media_metadata = json_encode($videoMeta);
        $model->save();
        $path = $request->video->store('videos/'.$pid.'/'.$model->id, 's3');
        $model->vid = $path;
        $model->save();

        if ($request->filled('description')) {
            $model->syncHashtagsFromCaption();
        }

        $profile->video_count = $profile->videos->count();
        $profile->save();
        VideoThumbnailJob::dispatch($model);
        VideoOptimizeJob::dispatch($model);

        return $this->success();
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateVideoRequest $request, $id)
    {
        $pid = $request->user()->profile_id;
        $video = Video::published()->findOrFail($id);
        $video->caption = $this->purify($request->caption);
        $video->can_download = $request->has('can_download') ? $request->boolean('can_download') : false;
        $video->comment_state = $request->has('can_comment') ? ($request->boolean('can_comment') ? 4 : 0) : 0;
        $video->is_pinned = $request->has('is_pinned') ? $request->boolean('is_pinned') : 0;
        $video->pinned_order = $request->has('is_pinned') ? Video::whereStatus(2)->whereProfileId($pid)->whereIsPinned(true)->count() + 1 : 0;
        $video->save();
        $video->syncHashtagsFromCaption();
        VideoService::deleteMediaData($video->id);
        $res = new VideoResource($video);

        return response()->json($res);
    }

    /**
     * Remove the specified resource from db/storage.
     */
    public function destroy(DeleteVideoRequest $request, $id)
    {
        $pid = $request->user()->profile_id;
        $video = Video::published()->findOrFail($id);
        VideoService::deleteMediaData($video->id);

        if (str_starts_with($video->vid, 'https://')) {

        } else {
            if (Storage::exists($video->vid)) {
                Storage::delete($video->vid);
            }
            $s3Path = 'videos/'.$video->profile_id.'/'.$video->id.'/';
            if (Storage::disk('s3')->exists($s3Path)) {
                Storage::disk('s3')->deleteDirectory($s3Path);
            }
        }
        $video->forceDelete();

        AccountService::del($pid);

        return $this->success();
    }

    /**
     * Like a video.
     */
    public function like(Request $request, $id)
    {
        $pid = $request->user()->profile_id;
        $video = Video::published()->find($id);

        if (! $video || $request->user()->cannot('create', [VideoLike::class, $video])) {
            return $this->error('Video not found or is unavailable', 404);
        }

        $like = VideoLike::firstOrCreate([
            'profile_id' => $pid,
            'video_id' => $video->id,
        ]);

        if ($like->wasRecentlyCreated) {
            $video->increment('likes');

            LikeService::addVideo($video->id, $pid);

            if ($pid !== $video->profile_id) {
                NotificationService::newVideoLike(
                    $video->profile_id,
                    $video->id,
                    $pid
                );
            }
        }

        $resp = (new VideoResource($video))->toArray($request);
        $resp['has_liked'] = true;

        return $resp;
    }

    /**
     * Unlike a video.
     */
    public function unlike(Request $request, $id)
    {
        $pid = $request->user()->profile_id;
        $video = Video::published()->find($id);

        if (! $video || $request->user()->cannot('create', [VideoLike::class, $video])) {
            return $this->error('Video not found or is unavailable', 404);
        }

        $res = VideoLike::where('profile_id', $pid)
            ->where('video_id', $video->id)
            ->first();

        if ($res) {
            $video->decrement('likes');
            LikeService::remVideo($video->id, $pid);
            $res->delete();
        } else {
            $resp = (new VideoResource($video))->toArray($request);

            return $resp;
        }

        $resp = (new VideoResource($video))->toArray($request);

        if ($res) {
            $resp['has_liked'] = false;
            $resp['likes'] = $resp['likes'] ? $resp['likes'] - 1 : 0;
        }

        return $resp;
    }

    /**
     * Get video comments.
     */
    public function comments(Request $request, $id)
    {
        $pid = $request->user()->profile_id;
        $video = Video::published()->canComment()->find($id);

        if (! $video || $request->user()->cannot('view', [Video::class, $video])) {
            return $this->error('Video not found or is unavailable or has comments disabled', 404);
        }

        $comments = Comment::withTrashed()
            ->whereVideoId($video->id)
            ->orderByDesc('id')
            ->cursorPaginate(10);

        return CommentResource::collection($comments);
    }

    /**
     * Get video comment replies.
     */
    public function commentsThread(Request $request, $id)
    {
        $this->validate($request, [
            'cr' => 'required|integer',
        ]);

        $pid = $request->user()->profile_id;
        $video = Video::published()->canComment()->find($id);

        if (! $video || $request->user()->cannot('view', [Video::class, $video])) {
            return $this->error('Video not found or is unavailable or has comments disabled', 404);
        }

        $comments = CommentReply::withTrashed()
            ->whereVideoId($video->id)
            ->whereCommentId($request->input('cr'))
            ->orderByDesc('id')
            ->cursorPaginate(3)
            ->withQueryString();

        return CommentReplyResource::collection($comments);
    }

    public function storeComment(StoreCommentRequest $request, $vid)
    {
        $pid = $request->user()->profile_id;
        $body = $this->purify($request->comment);

        $parentId = $request->filled('parent_id') ? $request->parent_id : false;
        $video = Video::published()->canComment()->find($vid);
        if (! $video || $request->user()->cannot('view', [Video::class, $video])) {
            return $this->error('Video not found or is unavailable or has comments disabled', 404);
        }

        if ($parentId) {
            $parent = Comment::whereVideoId($vid)->findOrFail($parentId);
            $comment = new CommentReply;
            $comment->comment_id = $parentId;
            $comment->video_id = $vid;
            $comment->profile_id = $pid;
            $comment->caption = $body;
            $comment->save();
            $parent->increment('replies');
            $parent->saveQuietly();

            if ($pid != $parent->profile_id) {
                NotificationService::newVideoCommentReply(
                    $pid,
                    $video->profile_id,
                    $video->id,
                    $parent->id,
                    $comment->id
                );
            }
        } else {
            $comment = new Comment;
            $comment->video_id = $vid;
            $comment->profile_id = $pid;
            $comment->caption = $body;
            $comment->save();

            if ($pid != $video->profile_id) {
                NotificationService::newVideoComment(
                    $pid,
                    $video->profile_id,
                    $video->id,
                    $comment->id
                );
            }
        }

        $video->recalculateCommentsCount();

        return $parentId ?
            CommentReplyResource::collection([$comment]) :
            CommentResource::collection([$comment]);
    }

    public function deleteComment(Request $request, $vid, $id)
    {
        $video = Video::published()->findOrFail($vid);
        $comment = Comment::withCount('children')->findOrFail($id);

        if ($comment->video_id !== $video->id || $comment->profile_id !== $request->user()->profile_id) {
            return $this->error('Record not found');
        }

        if ($comment->children_count) {
            $comment->update(['caption' => null, 'status' => 'deleted_by_user']);
            $comment->delete();
        } else {
            $comment->forceDelete();
        }

        $video->recalculateCommentsCount();

        return $this->success();
    }

    public function deleteCommentReply(Request $request, $vid, $pid, $id)
    {
        $video = Video::published()->findOrFail($vid);
        $parent = Comment::withTrashed()->withCount('children')->whereVideoId($vid)->findOrFail($pid);
        $comment = CommentReply::whereVideoId($video->id)
            ->whereProfileId($request->user()->profile_id)
            ->findOrFail($id);
        if ($parent->status != 'active' && $parent->children_count === 1) {
            $comment->forceDelete();
            $parent->forceDelete();
        } else {
            $parent->decrement('replies');
            $comment->forceDelete();
        }

        $video->recalculateCommentsCount();

        return $this->success();
    }

    public function storeCommentReplyLike(Request $request, $vid, $pid, $id)
    {
        $profileId = $request->user()->profile_id;

        $video = Video::published()->canComment()->find($vid);

        if (! $video || $request->user()->cannot('view', [Video::class, $video])) {
            return $this->error('Video not found or is unavailable or has comments disabled', 404);
        }

        $comment = CommentReply::whereVideoId($vid)->whereCommentId($pid)->findOrFail($id);

        $commentLike = CommentReplyLike::updateOrCreate([
            'profile_id' => $profileId,
            'comment_id' => $comment->id,
        ]);

        Notification::updateOrCreate([
            'user_id' => $profileId,
            'profile_id' => $comment->profile_id,
            'video_id' => $vid,
            'type' => Notification::VIDEO_COMMENT_REPLY_LIKE,
            'comment_reply_id' => $id,
        ]);

        if ($commentLike->wasRecentlyCreated) {
            $comment->increment('likes');
            $comment->saveQuietly();
            $video->increment('likes');
            $video->saveQuietly();
        }
        // CommentLikePipeline::dispatch($commentLike)->onQueue('comment-like');

        return (new CommentReplyResource($comment))->toArray($request);
    }

    public function storeCommentReplyUnlike(Request $request, $vid, $pid, $id)
    {
        $profileId = $request->user()->profile_id;

        $video = Video::published()->canComment()->find($vid);

        if (! $video || $request->user()->cannot('view', [Video::class, $video])) {
            return $this->error('Video not found or is unavailable or has comments disabled', 404);
        }

        $comment = CommentReply::whereVideoId($vid)->whereCommentId($pid)->findOrFail($id);

        $commentLike = CommentReplyLike::where([
            'profile_id' => $profileId,
            'comment_id' => $id,
        ])->first();

        if (! $commentLike) {
            return $this->success();
        }

        Notification::whereType(Notification::VIDEO_COMMENT_REPLY_LIKE)
            ->whereUserId($profileId)
            ->whereCommentReplyId($id)
            ->delete();

        $commentLike->delete();
        if ($comment->likes) {
            $comment->decrement('likes');
            $comment->saveQuietly();
        }
        if ($video->likes) {
            $video->decrement('likes');
            $video->saveQuietly();
        }

        return $this->success();
    }

    public function storeCommentLike(Request $request, $vid, $id)
    {
        $pid = $request->user()->profile_id;

        $video = Video::published()->canComment()->find($vid);

        if (! $video || $request->user()->cannot('view', [Video::class, $video])) {
            return $this->error('Video not found or is unavailable or has comments disabled', 404);
        }

        $comment = Comment::whereVideoId($vid)->findOrFail($id);

        $commentLike = CommentLike::updateOrCreate([
            'profile_id' => $pid,
            'comment_id' => $comment->id,
        ]);

        Notification::updateOrCreate([
            'user_id' => $pid,
            'profile_id' => $comment->profile_id,
            'video_id' => $vid,
            'type' => Notification::VIDEO_COMMENT_LIKE,
            'comment_id' => $id,
        ]);

        if ($commentLike->wasRecentlyCreated) {
            $comment->increment('likes');
            $comment->saveQuietly();
            $video->increment('likes');
            $video->saveQuietly();
        }
        // CommentLikePipeline::dispatch($commentLike)->onQueue('comment-like');

        return (new CommentResource($comment))->toArray($request);
    }

    public function storeCommentUnlike(Request $request, $vid, $id)
    {
        $pid = $request->user()->profile_id;

        $video = Video::published()->canComment()->find($vid);

        if (! $video || $request->user()->cannot('view', [Video::class, $video])) {
            return $this->error('Video not found or is unavailable or has comments disabled', 404);
        }

        $comment = Comment::whereVideoId($vid)->findOrFail($id);

        $commentLike = CommentLike::where([
            'profile_id' => $pid,
            'comment_id' => $id,
        ])->first();

        if (! $commentLike) {
            return $this->success();
        }

        Notification::whereType(Notification::VIDEO_COMMENT_LIKE)
            ->whereUserId($pid)
            ->whereCommentId($id)
            ->delete();

        $commentLike->delete();
        if ($comment->likes) {
            $comment->decrement('likes');
            $comment->saveQuietly();
        }
        if ($video->likes) {
            $video->decrement('likes');
            $video->saveQuietly();
        }

        return $this->success();
    }

    public function getAutocompleteMention(GetMentionAutocomplete $request)
    {
        $query = $request->input('q');
        $limit = $request->input('limit', 6);

        $res = Profile::where('username', 'like', $query.'%')->limit($limit)->get();

        return ProfileResource::collection($res);
    }

    public function getAutocompleteHashtag(GetMentionAutocomplete $request)
    {
        $query = $request->input('q');
        $limit = $request->input('limit', 6);

        $res = Hashtag::where('name', 'like', $query.'%')->limit($limit)->get();

        return HashtagResource::collection($res);
    }
}
