<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Traits\ApiHelpers;
use App\Http\Controllers\Controller;
use App\Http\Requests\DeleteVideoRequest;
use App\Http\Requests\GetMentionAutocomplete;
use App\Http\Requests\GetTagAutocomplete;
use App\Http\Requests\StoreCommentReplyUpdateRequest;
use App\Http\Requests\StoreCommentRequest;
use App\Http\Requests\StoreCommentUpdateRequest;
use App\Http\Requests\StoreVideoRequest;
use App\Http\Requests\UpdateVideoRequest;
use App\Http\Resources\CommentCaptionEditResource;
use App\Http\Resources\CommentReplyCaptionEditResource;
use App\Http\Resources\CommentReplyResource;
use App\Http\Resources\CommentResource;
use App\Http\Resources\HashtagResource;
use App\Http\Resources\ProfileResource;
use App\Http\Resources\VideoCaptionEditResource;
use App\Http\Resources\VideoLikeResource;
use App\Http\Resources\VideoRepostResource;
use App\Http\Resources\VideoResource;
use App\Jobs\Federation\DeliverCommentLikeActivity;
use App\Jobs\Federation\DeliverCommentReplyLikeActivity;
use App\Jobs\Federation\DeliverUndoCommentLikeActivity;
use App\Jobs\Federation\DeliverUndoCommentReplyLikeActivity;
use App\Jobs\Federation\DeliverUndoVideoLikeActivity;
use App\Jobs\Federation\DeliverVideoLikeActivity;
use App\Jobs\Video\VideoOptimizeJob;
use App\Jobs\Video\VideoProcessingCompleteJob;
use App\Jobs\Video\VideoThumbnailJob;
use App\Models\Comment;
use App\Models\CommentCaptionEdit;
use App\Models\CommentLike;
use App\Models\CommentReply;
use App\Models\CommentReplyCaptionEdit;
use App\Models\CommentReplyLike;
use App\Models\Hashtag;
use App\Models\Profile;
use App\Models\Video;
use App\Models\VideoBookmark;
use App\Models\VideoCaptionEdit;
use App\Models\VideoLike;
use App\Models\VideoRepost;
use App\Services\AccountService;
use App\Services\ConfigService;
use App\Services\FederationDispatcher;
use App\Services\LikeService;
use App\Services\SanitizeService;
use App\Services\UserActivityService;
use App\Services\VideoService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class VideoController extends Controller
{
    use ApiHelpers;

    public function __construct()
    {
        $this->middleware('auth');
    }

    public function showAutocompleteTags(GetTagAutocomplete $request)
    {
        $validated = $request->validate(['q' => 'required|alpha_dash|min:2|max:60']);

        $q = trim($validated['q']);

        $escaped = $this->escapeLike($q);

        $hashtags = Hashtag::where('name', 'like', $escaped.'%')->whereCanSearch(true)->orderByDesc('count')->limit(10)->get();

        return HashtagResource::collection($hashtags);
    }

    public function showAutocompleteAccounts(GetMentionAutocomplete $request)
    {
        $validated = $request->validate([
            'q' => [
                'required',
                'string',
                'min:2',
                'max:90',
                'regex:/^[A-Za-z0-9.\-_@]+$/',
            ],
        ]);

        $q = trim($validated['q']);

        $escaped = $this->escapeLike($q);

        $profiles = Profile::where('username', 'like', $escaped.'%')->whereStatus(1)->where('is_hidden', false)->orderByDesc('followers')->limit(10)->get();

        return ProfileResource::collection($profiles);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreVideoRequest $request)
    {
        set_time_limit(300);
        ini_set('max_execution_time', '300');

        $pid = $request->user()->profile_id;
        app(UserActivityService::class)->markActive($request->user());
        $profile = Profile::findOrFail($pid);
        $videoFile = $request->file('video');

        $videoMeta = [
            'size' => ceil($videoFile->getSize() / 1024),
            'name' => $videoFile->getClientOriginalName(),
            'mime' => $videoFile->getMimeType(),
        ];

        $model = null;
        $s3Path = null;

        try {
            DB::beginTransaction();

            $model = new Video;
            $model->profile_id = $pid;
            $model->caption = app(SanitizeService::class)->cleanPlainText($request->description);
            $model->size_kb = intval($videoMeta['size']);
            $model->is_sensitive = $request->filled('is_sensitive') ? (bool) $request->boolean('is_sensitive') : false;
            $model->comment_state = $request->filled('comment_state') ? ($request->input('comment_state') == 4 ? 4 : 0) : 4;
            $model->can_download = $request->filled('can_download') ? $request->boolean('can_download') : false;
            $model->can_duet = $request->filled('can_duet') ? $request->boolean('can_duet') : false;
            $model->can_stitch = $request->filled('can_stitch') ? $request->boolean('can_stitch') : false;
            $model->alt_text = $request->filled('alt_text') ? app(SanitizeService::class)->cleanPlainText($request->alt_text) : null;
            $model->contains_ai = $request->filled('contains_ai') ? $request->boolean('contains_ai') : false;
            $model->contains_ad = $request->filled('contains_ad') ? $request->boolean('contains_ad') : false;
            $model->lang = $request->filled('lang') ? $request->input('lang') : null;
            // @phpstan-ignore-next-line
            $model->media_metadata = $videoMeta;
            $model->save();

            try {
                $s3Path = $request->video->store('videos/'.$pid.'/'.$model->id, 's3');

                if (! $s3Path) {
                    throw new \Exception('Failed to upload video to S3');
                }

                $model->vid = $s3Path;
                $model->save();

            } catch (\Exception $e) {
                if (config('logging.dev_log')) {
                    Log::error('S3 upload failed for video', [
                        'user_id' => $request->user()->id,
                        'video_id' => $model->id,
                        'error' => $e->getMessage(),
                    ]);
                }

                throw new \Exception('Failed to upload video file. Please try again.');
            }

            if ($request->filled('description')) {
                $model->syncHashtagsFromCaption();
                $model->syncMentionsFromCaption();
            }

            $profile->video_count = $profile->videos->count();
            $profile->save();

            DB::commit();

            $model->processing_status = 'processing';
            $model->save();

            VideoThumbnailJob::withChain([
                new VideoOptimizeJob($model),
                new VideoProcessingCompleteJob($model),
            ])->dispatch($model);

            return $this->success();

        } catch (\Exception $e) {
            DB::rollBack();

            if ($s3Path) {
                try {
                    Storage::disk('s3')->delete($s3Path);

                    if (config('logging.dev_log')) {
                        Log::info('Cleaned up S3 file after error', ['path' => $s3Path]);
                    }
                } catch (\Exception $deleteError) {
                    Log::error('Failed to delete S3 file during cleanup', [
                        'path' => $s3Path,
                        'error' => $deleteError->getMessage(),
                    ]);
                }
            }

            if ($model && $model->exists) {
                try {
                    $model->delete();
                    if (config('logging.dev_log')) {
                        Log::info('Cleaned up video model after error', ['video_id' => $model->id]);
                    }
                } catch (\Exception $deleteError) {
                    if (config('logging.dev_log')) {
                        Log::error('Failed to delete video model during cleanup', [
                            'video_id' => $model->id,
                            'error' => $deleteError->getMessage(),
                        ]);
                    }
                }
            }

            if (config('logging.dev_log')) {
                Log::error('Video upload failed', [
                    'user_id' => $request->user()->id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => $e->getMessage() ?: 'An error occurred while uploading your video. Please try again.',
                'error' => 'Upload failed',
            ], 500);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateVideoRequest $request, $id)
    {
        $pid = $request->user()->profile_id;
        $updatedCaption = $this->purifyText($request->caption);
        $video = Video::published()->findOrFail($id);
        if ($video->caption !== $updatedCaption) {
            VideoCaptionEdit::create([
                'video_id' => $video->id,
                'profile_id' => $video->profile_id,
                'caption' => $video->caption,
            ]);
        }
        $video->caption = $updatedCaption;
        $video->can_download = $request->has('can_download') ? $request->boolean('can_download') : false;
        $video->comment_state = $request->has('can_comment') ? ($request->boolean('can_comment') ? 4 : 0) : 0;
        $video->is_pinned = $request->has('is_pinned') ? $request->boolean('is_pinned') : 0;
        $video->pinned_order = $request->has('is_pinned') ? Video::whereStatus(2)->whereProfileId($pid)->whereIsPinned(true)->count() + 1 : 0;
        $video->alt_text = $request->filled('alt_text') ? app(SanitizeService::class)->cleanPlainText($request->alt_text) : null;
        $video->can_duet = $request->has('can_duet') ? $request->boolean('can_duet') : $video->can_duet;
        $video->can_stitch = $request->has('can_stitch') ? $request->boolean('can_stitch') : $video->can_stitch;
        if ($request->filled('lang') && $request->input('lang') != $video->lang) {
            $video->lang = $request->input('lang');
        }
        if (! $video->is_sensitive && $request->has('is_sensitive') && $request->boolean('is_sensitive')) {
            $video->is_sensitive = true;
        }
        if (! $video->contains_ad && $request->has('contains_ad') && $request->boolean('contains_ad')) {
            $video->contains_ad = true;
        }
        if (! $video->contains_ai && $request->has('contains_ai') && $request->boolean('contains_ai')) {
            $video->contains_ai = true;
        }
        $video->is_edited = true;
        $video->save();
        $video->syncHashtagsFromCaption();
        $video->syncMentionsFromCaption();
        VideoService::deleteMediaData($video->id);
        $res = new VideoResource($video);
        $config = app(ConfigService::class);

        if ($config->federation()) {
            app(FederationDispatcher::class)->dispatchVideoUpdate($video->fresh());
        }

        return response()->json($res);
    }

    public function showVideoHistory(Request $request, $id)
    {
        $video = Video::published()->findOrFail($id);
        $paginator = VideoCaptionEdit::whereVideoId($video->id)->orderByDesc('id')->cursorPaginate(2)->withQueryString();
        if (! $request->has('cursor')) {
            $latest = new VideoCaptionEdit([
                'video_id' => (string) $video->id,
                'profile_id' => (string) $video->profile_id,
                'caption' => $video->caption,
                'updated_at' => $video->updated_at,
            ]);
            $collection = $paginator->getCollection();
            $collection->prepend($latest);
            $paginator->setCollection($collection->values());
        }

        return VideoCaptionEditResource::collection($paginator);
    }

    public function showCommentsHistory(Request $request, $vid, $cid)
    {
        $video = Video::published()->findOrFail($vid);
        $comment = Comment::whereVideoId($video->id)->findOrFail($cid);
        $paginator = CommentCaptionEdit::whereCommentId($comment->id)->orderByDesc('id')->cursorPaginate(2)->withQueryString();
        if (! $request->has('cursor')) {
            $latest = new CommentCaptionEdit([
                'comment_id' => (string) $comment->id,
                'profile_id' => (string) $comment->profile_id,
                'caption' => $comment->caption,
                'updated_at' => $comment->updated_at,
            ]);
            $collection = $paginator->getCollection();
            $collection->prepend($latest);
            $paginator->setCollection($collection->values());
        }

        return CommentCaptionEditResource::collection($paginator);
    }

    public function showCommentReplyHistory(Request $request, $vid, $cid, $id)
    {
        $video = Video::published()->findOrFail($vid);
        $comment = CommentReply::whereCommentId($cid)->findOrFail($id);
        $paginator = CommentReplyCaptionEdit::whereCommentId($comment->id)->orderByDesc('id')->cursorPaginate(2)->withQueryString();
        if (! $request->has('cursor')) {
            $latest = new CommentReplyCaptionEdit([
                'comment_id' => (string) $comment->id,
                'profile_id' => (string) $comment->profile_id,
                'caption' => $comment->caption,
                'updated_at' => $comment->updated_at,
            ]);
            $collection = $paginator->getCollection();
            $collection->prepend($latest);
            $paginator->setCollection($collection->values());
        }

        return CommentReplyCaptionEditResource::collection($paginator);
    }

    /**
     * Remove the specified resource from db/storage.
     */
    public function destroy(DeleteVideoRequest $request, $id)
    {
        $pid = $request->user()->profile_id;
        $video = Video::published()->findOrFail($id);
        VideoService::deleteMediaData($video->id);
        $videoId = $video->id;
        $videoObjectUrl = $video->getObjectUrl();
        $actor = $video->profile;

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
        $config = app(ConfigService::class);

        if ($config->federation()) {
            app(FederationDispatcher::class)->dispatchVideoDeleteToAllKnownInboxes($actor, $videoId, $videoObjectUrl);
        }

        $video->forceDelete();
        VideoService::deleteMediaData($videoId);
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
            $video->likes = VideoLike::whereVideoId($video->id)->count();
            $video->saveQuietly();

            app(LikeService::class)->addVideoLike((string) $video->id, (string) $pid);

            if ($video->uri) {
                $config = app(ConfigService::class);
                if ($config->federation()) {
                    DeliverVideoLikeActivity::dispatch($request->user()->profile, $video, $like)->onQueue('activitypub-out');
                }
            }
        }

        $resp = (new VideoResource($video))->toArray($request);
        $resp['has_liked'] = true;
        $resp['likes'] = $video->likes;

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
            if ($video->uri) {
                $config = app(ConfigService::class);
                if ($config->federation()) {
                    DeliverUndoVideoLikeActivity::dispatch(
                        $request->user()->profile,
                        $video,
                        $video->getObjectUrl(),
                        $res->id
                    )->onQueue('activitypub-out');
                }
            }
            app(LikeService::class)->removeVideoLike((string) $video->id, (string) $pid);
            $res->delete();
            $video->likes = VideoLike::whereVideoId($video->id)->count();
            $video->saveQuietly();
        } else {
            $resp = (new VideoResource($video))->toArray($request);

            return $resp;
        }

        $resp = (new VideoResource($video))->toArray($request);
        $resp['has_liked'] = false;
        $resp['likes'] = $video->likes;

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
            ->where('is_hidden', false)
            ->orderByDesc('id')
            ->cursorPaginate(10);

        return CommentResource::collection($comments);
    }

    public function showHiddenComments(Request $request, $id)
    {
        $pid = $request->user()->profile_id;
        $video = Video::published()->canComment()->find($id);

        if (! $video || $request->user()->cannot('view', [Video::class, $video])) {
            return $this->error('Video not found or is unavailable or has comments disabled', 404);
        }

        $comments = Comment::withTrashed()
            ->whereVideoId($video->id)
            ->where('is_hidden', true)
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
            ->where('is_hidden', false)
            ->orderByDesc('id')
            ->cursorPaginate(3)
            ->withQueryString();

        return CommentReplyResource::collection($comments);
    }

    public function storeComment(StoreCommentRequest $request, $vid)
    {
        $pid = $request->user()->profile_id;
        $body = $this->purifyText($request->comment);

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
            $comment->status = 'active';
            $comment->save();
            $parent->recalculateReplies();
            $parent->saveQuietly();

            $comment->syncHashtagsFromCaption();
            $comment->syncMentionsFromCaption();

            $config = app(ConfigService::class);
            if ($config->federation()) {
                app(FederationDispatcher::class)->dispatchCommentReplyCreation($comment);
            }
        } else {
            $comment = new Comment;
            $comment->video_id = $vid;
            $comment->profile_id = $pid;
            $comment->caption = $body;
            $comment->status = 'active';
            $comment->save();

            $comment->syncHashtagsFromCaption();
            $comment->syncMentionsFromCaption();

            $config = app(ConfigService::class);
            if ($config->federation()) {
                app(FederationDispatcher::class)->dispatchCommentCreation($comment);
            }
        }

        $video->recalculateCommentsCount();

        return $parentId ?
            CommentReplyResource::collection([$comment]) :
            CommentResource::collection([$comment]);
    }

    public function storeCommentUpdate(StoreCommentUpdateRequest $request, $vid)
    {
        $pid = $request->user()->profile_id;
        $body = $this->purifyText($request->comment);

        $video = Video::published()->canComment()->find($vid);
        if (! $video || $request->user()->cannot('view', [Video::class, $video])) {
            return $this->error('Video not found or is unavailable or has comments disabled', 404);
        }

        $comment = Comment::whereProfileId($pid)->findOrFail($request->id);
        CommentCaptionEdit::create([
            'comment_id' => $comment->id,
            'profile_id' => $pid,
            'caption' => $comment->caption,
        ]);

        $comment->update(['caption' => $body, 'is_edited' => true]);
        $comment->syncHashtagsFromCaption();
        $comment->syncMentionsFromCaption();

        $video->recalculateCommentsCount();

        $config = app(ConfigService::class);
        if ($config->federation()) {
            app(FederationDispatcher::class)->dispatchCommentUpdate($comment->fresh());
        }

        return CommentResource::collection([$comment]);
    }

    public function storeCommentReplyUpdate(StoreCommentReplyUpdateRequest $request, $vid)
    {
        $pid = $request->user()->profile_id;
        $body = $this->purifyText($request->comment);

        $video = Video::published()->canComment()->find($vid);
        if (! $video || $request->user()->cannot('view', [Video::class, $video])) {
            return $this->error('Video not found or is unavailable or has comments disabled', 404);
        }

        $comment = CommentReply::whereProfileId($pid)->findOrFail($request->id);
        CommentReplyCaptionEdit::create([
            'comment_id' => $comment->id,
            'profile_id' => $pid,
            'caption' => $comment->caption,
        ]);

        $comment->update(['caption' => $body, 'is_edited' => true]);
        $comment->syncHashtagsFromCaption();
        $comment->syncMentionsFromCaption();

        $config = app(ConfigService::class);
        if ($config->federation()) {
            app(FederationDispatcher::class)->dispatchCommentReplyUpdate(
                $comment->fresh()
            );
        }

        $video->recalculateCommentsCount();

        return CommentReplyResource::collection([$comment]);
    }

    public function deleteComment(Request $request, $vid, $id)
    {
        $video = Video::published()->findOrFail($vid);
        $comment = Comment::withCount('children')->findOrFail($id);
        $commentId = $comment->id;
        $commentObjectUrl = $comment->getObjectUrl();

        if ($comment->video_id !== $video->id || $comment->profile_id !== $request->user()->profile_id) {
            return $this->error('Record not found');
        }

        $config = app(ConfigService::class);
        if ($config->federation()) {
            app(FederationDispatcher::class)->dispatchCommentDeleteToAllKnownInboxes(
                $request->user()->profile,
                $commentId,
                $commentObjectUrl
            );
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
        $commentId = $comment->id;
        $commentObjectUrl = $comment->getObjectUrl();

        $config = app(ConfigService::class);
        if ($config->federation()) {
            app(FederationDispatcher::class)->dispatchCommentReplyDeleteToAllKnownInboxes(
                $request->user()->profile,
                $commentId,
                $commentObjectUrl
            );
        }
        if ($parent->status != 'active' && $parent->children_count === 1) {
            $comment->forceDelete();
            $parent->forceDelete();
        } else {
            $comment->forceDelete();
            $parent->recalculateReplies();
        }

        $video->recalculateCommentsCount();

        return $this->success();
    }

    public function hideComment(Request $request, $vid, $id)
    {
        $video = Video::published()->findOrFail($vid);
        $comment = Comment::findOrFail($id);

        if ($comment->video_id !== $video->id || $video->profile_id != $request->user()->profile_id) {
            return $this->error('Record not found');
        }
        $video->update(['has_hidden_comments' => true]);
        $comment->update(['is_hidden' => true]);

        return $this->success();
    }

    public function hideCommentReply(Request $request, $vid, $pid, $id)
    {
        $video = Video::published()->findOrFail($vid);
        $comment = CommentReply::findOrFail($id);

        if ($comment->video_id !== $video->id ||
            $video->profile_id != $request->user()->profile_id ||
            $comment->comment_id != $pid
        ) {
            return $this->error('Record not found');
        }

        $video->update(['has_hidden_comments' => true]);
        $comment->update(['is_hidden' => true]);

        return $this->success();
    }

    public function unhideComment(Request $request, $vid, $id)
    {
        $video = Video::published()->findOrFail($vid);
        $comment = Comment::findOrFail($id);

        if ($comment->video_id !== $video->id || $video->profile_id != $request->user()->profile_id) {
            return $this->error('Record not found');
        }
        $comment->update(['is_hidden' => false]);
        $commentsHidden = Comment::whereVideoId($video->id)->where('is_hidden', true)->count();
        $video->update(['has_hidden_comments' => $commentsHidden]);

        return new CommentResource($comment->fresh());
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

        if ($commentLike->wasRecentlyCreated) {
            $comment->increment('likes');
            $comment->saveQuietly();
            $video->increment('likes');
            $video->saveQuietly();
        }

        if ($comment->ap_id) {
            $config = app(ConfigService::class);
            if ($config->federation()) {
                DeliverCommentReplyLikeActivity::dispatch(
                    $request->user()->profile,
                    $comment->profile,
                    $commentLike,
                    $comment->getObjectUrl()
                )->onQueue('activitypub-out');
            }
        }

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

        $commentLikeId = $commentLike->id;
        if ($comment->ap_id) {
            $config = app(ConfigService::class);
            if ($config->federation()) {
                DeliverUndoCommentReplyLikeActivity::dispatch(
                    $request->user()->profile,
                    $comment->profile,
                    $commentLikeId,
                    $comment->getObjectUrl()
                )->onQueue('activitypub-out');
            }
        }

        $commentLike->delete();
        if ($comment->likes) {
            $comment->decrement('likes');
            $comment->saveQuietly();
        }
        if ($video->likes) {
            $video->decrement('likes');
            $video->saveQuietly();
        }

        return (new CommentReplyResource($comment))->toArray($request);
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

        if ($comment->ap_id) {
            $config = app(ConfigService::class);
            if ($config->federation()) {
                $actorProfile = $request->user()->profile;
                $targetProfile = $comment->profile;
                $commentObjectUrl = $comment->getObjectUrl();
                DeliverCommentLikeActivity::dispatch(
                    $actorProfile,
                    $targetProfile,
                    $commentLike,
                    $commentObjectUrl
                )->onQueue('activitypub-out');
            }
        }

        if ($commentLike->wasRecentlyCreated) {
            $comment->increment('likes');
            $comment->saveQuietly();
            $video->increment('likes');
            $video->saveQuietly();
        }

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
        if ($comment->ap_id) {
            $commentObjectUrl = $comment->getObjectUrl();
            $commentId = $comment->id;
            $config = app(ConfigService::class);
            if ($config->federation()) {
                $actorProfile = $request->user()->profile;
                $targetProfile = $comment->profile;
                DeliverUndoCommentLikeActivity::dispatch(
                    $actorProfile,
                    $targetProfile,
                    $commentId,
                    $commentObjectUrl
                )->onQueue('activitypub-out');
            }
        }

        $commentLike->delete();
        if ($comment->likes) {
            $comment->decrement('likes');
            $comment->saveQuietly();
        }
        if ($video->likes) {
            $video->decrement('likes');
            $video->saveQuietly();
        }

        return (new CommentResource($comment))->toArray($request);
    }

    public function showVideoLikes(Request $request, $id)
    {
        $user = $request->user();
        $pid = $user->profile_id;

        $video = Video::published()->findOrFail($id);
        $isAuth = $video->profile_id == $pid || $user->is_admin;

        $likes = VideoLike::whereVideoId($id)
            ->join('profiles', 'profiles.id', '=', 'video_likes.profile_id')
            ->where('profiles.status', 1);

        if ($isAuth) {
            $res = $likes->leftJoin('followers as auth_following', function ($join) use ($pid) {
                $join->on('auth_following.following_id', '=', 'video_likes.profile_id')
                    ->where('auth_following.profile_id', '=', $pid);
            })
                ->select([
                    'video_likes.*',
                    DB::raw('CASE WHEN auth_following.id IS NOT NULL THEN 1 ELSE 0 END as is_following'),
                ])
                ->orderBy('video_likes.created_at', 'desc')
                ->cursorPaginate(10);
        } else {
            $res = $likes->select('video_likes.*')
                ->orderBy('video_likes.created_at', 'desc')
                ->limit(10)
                ->get();
        }

        return VideoLikeResource::collection($res);
    }

    public function showVideoShares(Request $request, $id)
    {
        $user = $request->user();
        $pid = $user->profile_id;

        $video = Video::published()->findOrFail($id);
        $isAuth = $video->profile_id == $pid || $user->is_admin;

        $shares = VideoRepost::whereVideoId($id)
            ->join('profiles', 'profiles.id', '=', 'video_reposts.profile_id')
            ->where('profiles.status', 1);

        if ($isAuth) {
            $res = $shares->leftJoin('followers as auth_following', function ($join) use ($pid) {
                $join->on('auth_following.following_id', '=', 'video_reposts.profile_id')
                    ->where('auth_following.profile_id', '=', $pid);
            })
                ->select([
                    'video_reposts.*',
                    DB::raw('CASE WHEN auth_following.id IS NOT NULL THEN 1 ELSE 0 END as is_following'),
                ])
                ->orderBy('video_reposts.created_at', 'desc')
                ->cursorPaginate(10);
        } else {
            $res = $shares->select('video_reposts.*')
                ->orderBy('video_reposts.created_at', 'desc')
                ->limit(10)
                ->get();
        }

        return VideoRepostResource::collection($res);
    }

    public function bookmark(Request $request, $id)
    {
        $user = $request->user();
        $pid = $user->profile_id;
        $video = Video::published()->findOrFail($id);

        $bookmark = VideoBookmark::firstOrCreate(
            ['profile_id' => $pid, 'video_id' => $video->id]
        );

        if ($bookmark->wasRecentlyCreated) {
            $video->increment('bookmarks');
        }
        // @phpstan-ignore-next-line
        $video->is_bookmarked = true;
        $resp = (new VideoResource($video))->toArray($request);

        return $resp;
    }

    public function unbookmark(Request $request, $id)
    {
        $user = $request->user();
        $pid = $user->profile_id;
        $video = Video::published()->findOrFail($id);

        $deleted = VideoBookmark::where(['profile_id' => $pid, 'video_id' => $id])->delete();

        if ($deleted && $video->bookmarks) {
            $video->decrement('bookmarks');
        }
        // @phpstan-ignore-next-line
        $video->is_bookmarked = false;
        $resp = (new VideoResource($video))->toArray($request);

        return $resp;
    }

    private function escapeLike(string $value): string
    {
        return str_replace(
            ['\\', '%', '_'],
            ['\\\\', '\\%', '\\_'],
            $value
        );
    }
}
