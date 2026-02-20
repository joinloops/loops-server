<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Traits\ApiHelpers;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAdminInviteRequest;
use App\Http\Resources\AdminHashtagResource;
use App\Http\Resources\AdminInstanceResource;
use App\Http\Resources\AdminInviteResource;
use App\Http\Resources\AdminProfileResource;
use App\Http\Resources\CommentReplyResource;
use App\Http\Resources\CommentResource;
use App\Http\Resources\ProfileResource;
use App\Http\Resources\ReportResource;
use App\Http\Resources\VideoResource;
use App\Jobs\Federation\FetchInstanceNodeinfo;
use App\Models\AdminInvite;
use App\Models\Comment;
use App\Models\CommentReply;
use App\Models\Follower;
use App\Models\Hashtag;
use App\Models\Instance;
use App\Models\Profile;
use App\Models\Report;
use App\Models\User;
use App\Models\Video;
use App\Services\AccountService;
use App\Services\AccountSuggestionService;
use App\Services\AdminAuditLogService;
use App\Services\ExploreService;
use App\Services\InstanceService;
use App\Services\NodeinfoCrawlerService;
use App\Services\SanitizeService;
use App\Services\VersionCheckService;
use App\Services\VideoService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AdminController extends Controller
{
    use ApiHelpers;

    public function __construct()
    {
        $this->middleware('auth');
    }

    public function videos(Request $request)
    {
        $search = $request->query('q');
        $sort = $request->query('sort');

        $query = Video::when($search, function ($query, $search) {
            $query->join('profiles', 'videos.profile_id', '=', 'profiles.id')
                ->where('profiles.username', 'like', '%'.$search.'%')
                ->select('videos.*');
        });

        $query = $this->applySorting($query, $sort);
        $videos = $query->orderByDesc('id')->cursorPaginate(10)->withQueryString();

        return VideoResource::collection($videos);
    }

    public function videoShow(Request $request, $id)
    {
        $video = Video::findOrFail($id);

        $res = (new VideoResource($video))->toArray($request);
        $res['status'] = $video->statusLabel();
        $res['media']['size'] = $video->size_kb;
        $res['hid'] = $video->hashid();
        $res['reported_count'] = Report::whereReportedVideoId($id)->count();

        return $this->data($res);
    }

    public function videoCommentsShow(Request $request, $id)
    {
        $video = Video::findOrFail($id);

        $comments = Comment::whereVideoId($video->id)
            ->orderByDesc('id')
            ->cursorPaginate(5);

        return CommentResource::collection($comments);
    }

    public function videoModerate(Request $request, $id)
    {
        $request->validate([
            'action' => 'required|in:unpublished,publish,delete',
        ]);

        $action = $request->input('action');

        if ($action === 'delete') {
            $video = Video::findOrFail($id);
            $pid = $video->profile_id;
            VideoService::deleteMediaData($video->id);

            if ($video->vid) {
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
            }

            app(AdminAuditLogService::class)->logVideoDelete($request->user(), $video, ['vid' => $video->id, 'caption' => $video->caption, 'profile_id' => $video->profile_id, 'likes' => $video->likes]);

            $video->forceDelete();

            AccountService::del($pid);

            return $this->success();
        }

        $video = Video::findOrFail($id);
        $video->status = $action == 'unpublished' ? 6 : 2;

        if ($action == 'unpublished') {
            app(AdminAuditLogService::class)->logVideoUnpublish($request->user(), $video, ['vid' => $video->id, 'profile_id' => $video->profile_id]);
        } else {
            app(AdminAuditLogService::class)->logVideoPublish($request->user(), $video, ['vid' => $video->id, 'profile_id' => $video->profile_id]);
        }
        $video->saveQuietly();
        VideoService::getMediaData($video->id, true);

        $res = (new VideoResource($video))->toArray($request);
        $res['status'] = $video->statusLabel();
        $res['media']['size'] = $video->size_kb;
        $res['hid'] = $video->hashid();

        return $this->data($res);
    }

    public function videoCommentsDelete(Request $request, $id)
    {
        $comment = Comment::withCount('children')->findOrFail($id);
        $video = Video::findOrFail($comment->video_id);
        app(AdminAuditLogService::class)->logVideoCommentDelete($request->user(), $video, ['vid' => $video->id, 'comment_id' => $comment->id, 'comment_profile_id' => $comment->profile_id, 'comment_caption' => $comment->caption, 'comment_likes' => $comment->likes]);
        if ($comment->children_count) {
            $comment->update(['caption' => null, 'status' => 'deleted_by_admin']);
            $comment->delete();
        } else {
            $comment->forceDelete();
        }

        $video->recalculateCommentsCount();

        return $this->success();
    }

    public function videoCommentsHide(Request $request, $id)
    {
        $comment = Comment::findOrFail($id);
        $video = Video::findOrFail($comment->video_id);
        app(AdminAuditLogService::class)->logVideoCommentHide($request->user(), $video, ['vid' => $video->id, 'comment_id' => $comment->id, 'comment_profile_id' => $comment->profile_id]);
        $comment->update(['is_hidden' => true]);
        $video->update(['has_hidden_comments' => true]);

        return $this->success();
    }

    public function videoCommentsUnhide(Request $request, $id)
    {
        $comment = Comment::withCount('children')->findOrFail($id);
        $video = Video::findOrFail($comment->video_id);
        app(AdminAuditLogService::class)->logVideoCommentUnhide($request->user(), $video, ['vid' => $video->id, 'comment_id' => $comment->id, 'comment_profile_id' => $comment->profile_id]);
        $comment->update(['is_hidden' => false]);
        $commentsHidden = Comment::whereVideoId($video->id)->where('is_hidden', true)->count();
        $video->update(['has_hidden_comments' => $commentsHidden]);

        return $this->success();
    }

    public function videoReplyHide(Request $request, $id)
    {
        $comment = CommentReply::findOrFail($id);
        $video = Video::findOrFail($comment->video_id);

        app(AdminAuditLogService::class)->logVideoCommentReplyHide(
            $request->user(),
            $video,
            [
                'comment_reply_id' => $comment->id,
            ]
        );

        $comment->update(['is_hidden' => true]);
        $video->update(['has_hidden_comments' => true]);

        return $this->success();
    }

    public function videoReplyUnhide(Request $request, $id)
    {
        $comment = CommentReply::findOrFail($id);
        $video = Video::findOrFail($comment->video_id);

        app(AdminAuditLogService::class)->logVideoCommentReplyUnhide(
            $request->user(),
            $video,
            [
                'comment_reply_id' => $comment->id,
            ]
        );

        $comment->update(['is_hidden' => false]);
        $totalHidden = DB::selectOne('
            SELECT (
                (SELECT COUNT(*) FROM comments WHERE video_id = :vid1 AND is_hidden = 1) + 
                (SELECT COUNT(*) FROM comment_replies WHERE video_id = :vid2 AND is_hidden = 1)
            ) as total',
            ['vid1' => $video->id, 'vid2' => $video->id]
        )->total;
        $video->update(['has_hidden_comments' => (bool) $totalHidden]);

        return $this->success();
    }

    public function videoReplyDelete(Request $request, $id)
    {
        $comment = CommentReply::findOrFail($id);
        $video = Video::findOrFail($comment->video_id);

        app(AdminAuditLogService::class)->logReportDeleteCommentReply(
            $request->user(),
            $video,
            [
                'vid' => $video->id,
                'parent_id' => $comment->comment_id,
                'comment_id' => $comment->id,
                'comment_profile_id' => $comment->profile_id,
                'comment_caption' => $comment->caption,
                'comment_likes' => $comment->likes,
            ]
        );

        $parent = $comment->parent;
        $comment->forceDelete();
        $parent->recalculateReplies();
        $video->decrement('comments');

        return $this->success();
    }

    public function profiles(Request $request)
    {
        $search = $request->query('q');
        $sort = $request->query('sort');
        $local = $request->query('local');

        $query = Profile::query();

        $query->select('profiles.*');

        if ($local == true) {
            $query->where('profiles.local', true);
        }

        if (! in_array($sort, ['disabled', 'suspended', 'deleted'])) {
            if ($local) {
                $query->join('users', 'profiles.id', '=', 'users.profile_id')
                    ->where('users.status', 1);
            } else {
                $query->where('status', 1);
            }
        }

        if (! empty($search)) {
            if (str_starts_with($search, 'bio:')) {
                $bio = trim(substr($search, 4));
                if (! empty($bio)) {
                    $query->where('profiles.bio', 'like', '%'.$bio.'%');
                }
            } elseif (str_starts_with($search, 'name:')) {
                $name = trim(substr($search, 5));
                if (! empty($name)) {
                    $query->where('profiles.name', 'like', '%'.$name.'%');
                }
            } elseif (str_starts_with($search, 'email:')) {
                $email = trim(substr($search, 6));
                if (! empty($email)) {
                    if (! $this->queryHasJoin($query, 'users')) {
                        $query->join('users', 'profiles.id', '=', 'users.profile_id');
                    }
                    $query->where('users.email', 'like', '%'.$email.'%');
                }
            } elseif (str_starts_with($search, 'invited_with:')) {
                $inviteId = trim(substr($search, 13));
                if (! empty($inviteId)) {
                    if (! $this->queryHasJoin($query, 'users')) {
                        $query->join('users', 'profiles.id', '=', 'users.profile_id');
                    }
                    $query->where('users.admin_invite_id', $inviteId);
                }
            } else {
                $query->where('profiles.username', 'like', '%'.$search.'%');
            }
        }

        $query = $this->applySortingJoin($query, $sort, 'profiles');

        $profiles = $query->cursorPaginate(10)->withQueryString();

        return AdminProfileResource::collection($profiles);
    }

    public function profileShow(Request $request, $id)
    {
        $profile = Profile::findOrFail($id);

        $res = (new ProfileResource($profile))->toArray($request);

        if ($profile->local) {
            $user = User::whereProfileId($id)->firstOrFail();
            $res['is_admin'] = (bool) $user->is_admin;
            $res['email'] = $user->email;
            $res['email_verified'] = (bool) $user->email_verified_at;
            $res['delete_after'] = $user->delete_after;
        }
        $res['comments_count'] = Comment::whereProfileId($profile->id)->count();
        $res['comment_replies_count'] = CommentReply::whereProfileId($profile->id)->count();
        $res['reports_created_count'] = Report::whereReporterProfileId($profile->id)->count();
        $res['reported_count'] = Report::totalReportsAgainstProfile($profile->id);
        $res['likes_count'] = AccountService::getAccountLikesCount($profile->id);
        $res['status'] = $profile->getStatusDescription();
        $res['admin_notes'] = $profile->admin_notes;
        $res['can_upload'] = (bool) $profile->can_upload;
        $res['can_comment'] = (bool) $profile->can_comment;
        $res['can_follow'] = (bool) $profile->can_follow;
        $res['can_like'] = (bool) $profile->can_like;

        return $this->data($res);
    }

    public function profilePermissionUpdate(Request $request, $id)
    {
        $validated = $request->validate([
            'can_upload' => 'sometimes|boolean',
            'can_follow' => 'sometimes|boolean',
            'can_comment' => 'sometimes|boolean',
            'can_like' => 'sometimes|boolean',
            'can_share' => 'sometimes|boolean',
        ]);

        $userValidated = $request->validate([
            'can_upload' => 'sometimes|boolean',
            'can_follow' => 'sometimes|boolean',
            'can_comment' => 'sometimes|boolean',
            'can_like' => 'sometimes|boolean',
        ]);

        $profile = Profile::find($id);

        if (! $profile) {
            return $this->error('Ooops!');
        }

        $oldValues = $profile->only(['can_upload', 'can_follow', 'can_comment', 'can_like', 'can_share']);

        $profile->update($validated);
        if ($profile->local) {
            if ($profile->user && $profile->user->is_admin) {
                return $this->success();
            }
            $user = User::whereProfileId($id)->firstOrFail();
            $user->update($userValidated);
        }

        app(AdminAuditLogService::class)->logProfileAdminPermissionUpdate($request->user(), $profile, ['old' => $oldValues, 'new' => $validated]);

        return $this->success();
    }

    public function profileSuspend(Request $request, $id)
    {
        $profile = Profile::find($id);

        if (! $profile) {
            return $this->error('Ooops!');
        }

        $profile->update(['status' => 6]);

        AccountService::del($profile->id);

        if ($profile->local) {
            DB::table('oauth_access_tokens')->where('user_id', $profile->user_id)->delete();
            DB::table('oauth_auth_codes')->where('user_id', $profile->user_id)->delete();
            DB::table('sessions')->where('user_id', $profile->user_id)->delete();
            $profile->user->update(['status' => 6]);
            $profile->user->videos()->whereIn('status', [2])->update(['status' => 6]);
            $profile->user->comments()->where('status', 'active')->update(['status' => 'account_pending_deletion']);
            $profile->user->commentReplies()->where('status', 'active')->update(['status' => 'account_pending_deletion']);
            $profile->user->actorNotifications()->update(['actor_state' => 6]);
            AccountService::del($profile->id);
        }

        AccountSuggestionService::removeFromAll($profile->id);

        app(AdminAuditLogService::class)->logProfileAdminSuspend($request->user(), $profile);

        return $this->success();
    }

    public function profileUnsuspend(Request $request, $id)
    {
        $profile = Profile::find($id);

        if (! $profile) {
            return $this->error('Ooops!');
        }

        $profile->update(['status' => 1]);

        if ($profile->local) {
            $profile->user->update(['status' => 1]);
            $profile->user->videos()->whereIn('status', [6])->update(['status' => 2]);
            $profile->user->comments()->where('status', 'account_pending_deletion')->update(['status' => 'active']);
            $profile->user->commentReplies()->where('status', 'account_pending_deletion')->update(['status' => 'active']);
            $profile->user->actorNotifications()->update(['actor_state' => 1]);
        }

        AccountService::del($profile->id);
        app(AdminAuditLogService::class)->logProfileAdminUnsuspend($request->user(), $profile);

        return $this->success();
    }

    public function profileAdminNoteUpdate(Request $request, $id)
    {
        $validated = $request->validate([
            'admin_note' => 'sometimes|nullable|string',
        ]);

        $profile = Profile::find($id);

        if (! $profile) {
            return $this->error('Ooops!');
        }

        $oldValues = $profile->only(['admin_notes']);

        app(AdminAuditLogService::class)->logProfileAdminNoteUpdate($request->user(), $profile, ['old' => $oldValues, 'new' => $validated]);

        $profile->admin_notes = $request->input('admin_note');
        $profile->save();

        return $this->success();
    }

    public function reportCount(Request $request)
    {
        $count = Report::where('admin_seen', false)->count();

        return $this->data(['count' => $count]);
    }

    public function reports(Request $request)
    {
        $search = $request->query('q');
        $sort = $request->query('sort', 'open');
        $dir = $sort === 'open' ? 'asc' : 'desc';

        if ($request->filled('q') && ! $request->has('sort')) {
            $sort = 'all';
        }

        $reports = Report::search($search)
            ->filterByStatus($sort)
            ->orderBy('id', $dir)
            ->cursorPaginate(10)
            ->withQueryString();

        return ReportResource::collection($reports);
    }

    public function reportShow(Request $request, $id)
    {
        $report = Report::findOrFail($id);

        return new ReportResource($report);
    }

    public function reportUpdateMarkAsNsfw(Request $request, $id)
    {
        $report = Report::whereNotNull('reported_video_id')->where('admin_seen', false)->findOrFail($id);
        $video = $report->video;
        $video->is_sensitive = true;
        $video->save();
        $report->admin_seen = true;
        $report->save();

        app(AdminAuditLogService::class)->logReportUpdateMarkAsNsfw($request->user(), $report, ['vid' => $video->id, 'profile_id' => $report->reporter_profile_id]);

        return $this->success();
    }

    public function reportMarkAsAi(Request $request, $id)
    {
        $report = Report::whereNotNull('reported_video_id')->where('admin_seen', false)->findOrFail($id);
        $video = $report->video;
        $video->contains_ai = true;
        $video->save();
        $report->admin_seen = true;
        $report->save();

        app(AdminAuditLogService::class)->logReportUpdateMarkAsAi($request->user(), $report, ['vid' => $video->id, 'profile_id' => $report->reporter_profile_id]);

        return $this->success();
    }

    public function reportMarkAsAd(Request $request, $id)
    {
        $report = Report::whereNotNull('reported_video_id')->where('admin_seen', false)->findOrFail($id);
        $video = $report->video;
        $video->contains_ad = true;
        $video->save();
        $report->admin_seen = true;
        $report->save();

        app(AdminAuditLogService::class)->logReportUpdateMarkAsAd($request->user(), $report, ['vid' => $video->id, 'profile_id' => $report->reporter_profile_id]);

        return $this->success();
    }

    public function reportMarkAsAiAndAd(Request $request, $id)
    {
        $report = Report::whereNotNull('reported_video_id')->where('admin_seen', false)->findOrFail($id);
        $video = $report->video;
        $video->contains_ad = true;
        $video->contains_ai = true;
        $video->save();
        $report->admin_seen = true;
        $report->save();

        app(AdminAuditLogService::class)->logReportUpdateMarkAsAiAndAd($request->user(), $report, ['vid' => $video->id, 'profile_id' => $report->reporter_profile_id]);

        return $this->success();
    }

    public function reportDismiss(Request $request, $id)
    {
        $report = Report::where('admin_seen', false)->findOrFail($id);
        $report->admin_seen = true;
        $report->save();

        app(AdminAuditLogService::class)->logReportDismiss($request->user(), $report, ['profile_id' => $report->reporter_profile_id]);

        return $this->success();
    }

    public function reportDismissAllByAccount(Request $request, $id)
    {
        $report = Report::where('admin_seen', false)->findOrFail($id);

        app(AdminAuditLogService::class)->logReportDismissAllByAccount($request->user(), $report, ['profile_id' => $report->reporter_profile_id]);

        Report::whereReporterProfileId($report->reporter_profile_id)
            ->update([
                'handled' => true,
                'admin_seen' => true,
            ]);

        return $this->success();
    }

    public function reportDeleteVideo(Request $request, $id)
    {
        $report = Report::whereNotNull('reported_video_id')->where('admin_seen', false)->findOrFail($id);
        $videoId = $report->reported_video_id;
        $report->admin_seen = true;
        $report->save();

        $video = Video::published()->findOrFail($videoId);
        app(AdminAuditLogService::class)->logReportDeleteVideo($request->user(), $report, ['vid' => $videoId, 'video_caption' => $video->caption, 'video_profile_id' => $video->profile_id, 'video_likes' => $video->likes, 'video_comments' => $video->comments]);
        $pid = $video->profile_id;
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

    }

    public function reportDeleteComment(Request $request, $id)
    {
        $report = Report::whereNotNull('reported_comment_id')->where('admin_seen', false)->findOrFail($id);
        $comment = Comment::withCount('children')->findOrFail($report->reported_comment_id);

        app(AdminAuditLogService::class)->logReportDeleteComment($request->user(), $report, ['vid' => $comment->video_id, 'comment_id' => $comment->id, 'comment_profile_id' => $comment->profile_id, 'comment_content' => $comment->caption]);

        $report->admin_seen = true;
        $report->save();
        $vid = $comment->video_id;
        if ($comment->children_count == 0) {
            $comment->forceDelete();
        } else {
            $comment->status = 'deleted_by_admin';
            $comment->save();
            $comment->delete();
            $report->admin_seen = true;
            $report->save();
        }
        if ($vid) {
            Video::findOrFail($vid)->recalculateCommentsCount();
        }

        return $this->success();
    }

    public function reportDeleteCommentReply(Request $request, $id)
    {
        $report = Report::whereNotNull('reported_comment_reply_id')->where('admin_seen', false)->findOrFail($id);
        $commentReply = CommentReply::with('parent')->findOrFail($report->reported_comment_reply_id);
        $vid = $commentReply->video_id;
        app(AdminAuditLogService::class)->logReportDeleteCommentReply($request->user(), $report, ['vid' => $vid, 'comment_id' => $commentReply->id, 'comment_profile_id' => $commentReply->profile_id, 'comment_parent_id' => $commentReply->comment_id, 'comment_content' => $commentReply->caption]);
        if ($commentReply->parent) {
            $commentReply->parent->decrement('replies');
        } else {
            $parent = Comment::withTrashed()->findOrFail($commentReply->comment_id);
            $parent->forceDelete();
        }
        $commentReply->forceDelete();
        if ($vid) {
            Video::findOrFail($vid)->recalculateCommentsCount();
        }

        return $this->success();
    }

    public function reportUpdateAdminNotes(Request $request, $id)
    {
        $request->validate([
            'content' => 'sometimes|nullable|max:1500',
        ]);
        $report = Report::findOrFail($id);
        $oldValues = $report->only(['admin_notes']);

        $report->admin_notes = $request->input('content');
        $report->save();

        app(AdminAuditLogService::class)->logReportAdminNotesUpdate($request->user(), $report, ['old' => $oldValues, 'new' => ['admin_notes' => $request->input('content')]]);

        return new ReportResource($report);
    }

    public function comments(Request $request)
    {
        $local = $request->query('local');

        $query = Comment::query();

        if ($local) {
            $query->whereNull('ap_id');
        }

        $search = $request->query('q');

        $sort = $request->query('sort');

        if (! empty($search)) {
            if (str_starts_with($search, 'user:')) {
                $username = trim(substr($search, 5));
                if (! empty($username)) {
                    $query->join('profiles', 'comments.profile_id', '=', 'profiles.id')
                        ->where('profiles.username', 'like', '%'.$username.'%')
                        ->select('comments.*');
                }
            } elseif (str_starts_with($search, 'video:')) {
                $videoId = trim(substr($search, 6));
                if (! empty($videoId)) {
                    $query->where('video_id', $videoId);
                }
            } else {
                $query->where('caption', 'like', '%'.$search.'%');
            }
        }

        if ($sort === 'is_hidden') {
            $sort = 'comments.is_hidden';
        } else {
            $query = $query->where('comments.is_hidden', false);
        }

        $query = $this->applySorting($query, $sort);

        $comments = $query->cursorPaginate(10)
            ->withQueryString();

        return CommentResource::collection($comments);
    }

    public function getComment(Request $request, $id)
    {
        $query = Comment::findOrFail($id);

        return new CommentResource($query);
    }

    public function replies(Request $request)
    {
        $local = $request->filled('local');

        $query = CommentReply::query();

        if ($local) {
            $query->whereNull('ap_id');
        }

        $search = $request->query('q');
        $sort = $request->query('sort');

        if (! empty($search)) {
            if (str_starts_with($search, 'user:')) {
                $username = trim(substr($search, 5));
                if (! empty($username)) {
                    $query->join('profiles', 'comment_replies.profile_id', '=', 'profiles.id')
                        ->where('profiles.username', 'like', '%'.$username.'%')
                        ->select('comment_replies.*');
                }
            } elseif (str_starts_with($search, 'video:')) {
                $videoId = trim(substr($search, 6));
                if (! empty($videoId)) {
                    $query->where('video_id', $videoId);
                }
            } else {
                $query->where('caption', 'like', '%'.$search.'%');
            }
        }

        if ($sort === 'is_hidden') {
            $sort = 'comment_replies.is_hidden';
        } else {
            $query = $query->where('comment_replies.is_hidden', false);
        }

        $query = $this->applySorting($query, $sort);

        $comments = $query->cursorPaginate(10)
            ->withQueryString();

        return CommentReplyResource::collection($comments);
    }

    public function hashtags(Request $request)
    {
        $q = $request->query('q');
        $sort = $request->query('sort');

        $query = Hashtag::when($q, function ($query, $q) {
            $query->where('name', 'like', $q.'%')->orderByDesc('count');
        });

        $query = $this->applySorting($query, $sort);

        $tags = $query->cursorPaginate(10)->withQueryString();

        return AdminHashtagResource::collection($tags);
    }

    public function getHashtag(Request $request, $id)
    {
        $query = Hashtag::findOrFail($id);

        return new AdminHashtagResource($query);
    }

    public function hashtagsUpdate(Request $request, $id)
    {
        $validated = $request->validate([
            'can_autolink' => 'sometimes|boolean',
            'can_search' => 'sometimes|boolean',
            'can_trend' => 'sometimes|boolean',
            'is_banned' => 'sometimes|boolean',
            'is_nsfw' => 'sometimes|boolean',
        ]);

        $hashtag = Hashtag::findOrFail($id);

        $oldValues = $hashtag->only(['can_autolink', 'can_search', 'can_trend', 'is_banned', 'is_nsfw']);

        $hashtag->update($validated);

        app(AdminAuditLogService::class)->logHashtagUpdate($request->user(), $hashtag, ['old' => $oldValues, 'new' => $validated]);

        app(ExploreService::class)->getTrendingTags(true);

        Cache::forget(ExploreService::GUEST_TAG_FEED_KEY.$hashtag->id);

        return $this->success();
    }

    public function instances(Request $request)
    {
        $search = $request->query('q');
        $sort = $request->query('sort', 'active');

        $query = Instance::when($sort == 'is_blocked', function ($query, $sort) {
            $query->whereFederationState(2);
        }, function ($query, $sort) {
            $query->whereFederationState(5);
        });

        if (! empty($search)) {
            if (str_starts_with($search, 'software:')) {
                $software = trim(substr($search, 9));
                if (! empty($software)) {
                    if ($software === 'unknown') {
                        $query->whereNull('software');
                    } else {
                        $query->where('software', 'like', $software.'%');
                    }
                }
            } elseif (str_starts_with($search, 'description:')) {
                $desc = trim(substr($search, 12));
                if (! empty($desc)) {
                    $query->where('description', 'like', '%'.$desc.'%');
                }
            } else {
                $query->where('domain', 'like', '%'.$search.'%');
            }
        }

        $query = $this->applySorting($query, $sort);

        $instances = $query->cursorPaginate(10)->withQueryString();

        return AdminInstanceResource::collection($instances);
    }

    public function instanceShow(Request $request, $id)
    {
        $instance = Instance::findOrFail($id);

        return new AdminInstanceResource($instance);
    }

    public function instanceShowUsers(Request $request, $id)
    {
        $instance = Instance::findOrFail($id);

        $q = $request->query('q');
        $sort = $request->query('sort');

        $query = Profile::whereDomain($instance->domain)->when($q, function ($query, $q) {
            $query->where('username', 'like', $q.'%')->orderByDesc('followers');
        });

        $query = $this->applySorting($query, $sort);

        $profiles = $query->cursorPaginate(10)->withQueryString();

        return ProfileResource::collection($profiles);
    }

    public function instanceShowVideos(Request $request, $id)
    {
        $instance = Instance::findOrFail($id);

        $q = $request->query('q');
        $sort = $request->query('sort');

        $query = Video::join('profiles', 'videos.profile_id', '=', 'profiles.id')
            ->where('profiles.domain', $instance->domain)
            ->select('videos.*', 'profiles.username', 'profiles.followers', 'profiles.domain')
            ->when($q, function ($query, $q) {
                $query->where('profiles.username', 'like', $q.'%')
                    ->orderBy('profiles.followers', 'desc');
            });

        $query = $this->applySortingJoin($query, $sort, 'videos');

        $videos = $query->cursorPaginate(10)->withQueryString();

        return VideoResource::collection($videos);
    }

    public function instanceShowComments(Request $request, $id)
    {
        $instance = Instance::findOrFail($id);

        $q = $request->query('q');
        $sort = $request->query('sort');

        $query = Comment::join('profiles', 'comments.profile_id', '=', 'profiles.id')
            ->where('profiles.domain', $instance->domain)
            ->select('comments.*', 'profiles.username', 'profiles.followers', 'profiles.domain')
            ->when($q, function ($query, $q) {
                $query->where('profiles.username', 'like', $q.'%')
                    ->orderBy('profiles.followers', 'desc');
            });

        $query = $this->applySortingJoin($query, $sort, 'comments');

        $comments = $query->cursorPaginate(10)->withQueryString();

        return CommentResource::collection($comments);
    }

    public function showInstanceReports(Request $request, $id)
    {
        $instance = Instance::findOrFail($id);

        $q = $request->query('q');
        $sort = $request->query('sort');

        $query = Report::whereDomain($instance->domain);

        $query = $this->applySorting($query, $sort);

        $reports = $query->cursorPaginate(3)->withQueryString();

        return ReportResource::collection($reports);
    }

    public function updateInstanceAdminNotes(Request $request, $id)
    {
        $instance = Instance::findOrFail($id);
        $oldValues = $instance->only(['admin_notes']);

        $validated = $request->validate([
            'admin_notes' => 'sometimes|string|nullable|max:1000',
        ]);
        $instance->update($validated);

        app(AdminAuditLogService::class)->logInstanceUpdateNotes($request->user(), $instance, ['old' => $oldValues, 'new' => $validated]);

        return $this->success();
    }

    public function updateInstanceSettings(Request $request, $id)
    {
        $instance = Instance::findOrFail($id);

        $oldValues = $instance->only(['allow_video_posts', 'allow_videos_in_fyf', 'federation_state']);

        $validated = $request->validate([
            'allow_video_posts' => 'sometimes|boolean',
            'allow_videos_in_fyf' => 'sometimes|boolean',
            'federation_state' => 'sometimes|integer|in:2,5',
        ]);

        $instance->update($validated);

        app(AdminAuditLogService::class)->logInstanceUpdateSettings($request->user(), $instance, ['old' => $oldValues, 'new' => $validated]);
        app(InstanceService::class)->flushStats();

        return $this->success();
    }

    public function updateInstanceRefreshData(Request $request, $id)
    {
        $instance = Instance::findOrFail($id);

        $instance->user_count = Profile::whereDomain($instance->domain)->count();
        $instance->video_count = Video::join('profiles', 'videos.profile_id', '=', 'profiles.id')->where('profiles.domain', $instance->domain)->count();
        $instance->comment_count = Comment::join('profiles', 'comments.profile_id', '=', 'profiles.id')->where('profiles.domain', $instance->domain)->count();
        $instance->reply_count = CommentReply::join('profiles', 'comment_replies.profile_id', '=', 'profiles.id')->where('profiles.domain', $instance->domain)->count();
        $instance->follower_count = Follower::join('profiles', 'followers.profile_id', '=', 'profiles.id')->where('profiles.domain', $instance->domain)->count();
        $instance->report_count = Report::where('domain', $instance->domain)->count();
        $instance->save();

        FetchInstanceNodeinfo::dispatch($instance)->onQueue('actor-update');
        app(InstanceService::class)->flushStats();

        return $this->success();
    }

    public function instanceActivate(Request $request, $id)
    {
        $instance = Instance::findOrFail($id);
        $instance->federation_state = 5;
        $instance->is_blocked = false;
        $instance->save();

        app(AdminAuditLogService::class)->logInstanceActivated($request->user(), $instance);
        app(SanitizeService::class)->getBannedDomains(true);
        app(InstanceService::class)->flushStats();

        return $this->success();
    }

    public function instanceSuspend(Request $request, $id)
    {
        $instance = Instance::findOrFail($id);
        $instance->federation_state = 2;
        $instance->is_blocked = true;
        $instance->save();

        app(AdminAuditLogService::class)->logInstanceSuspended($request->user(), $instance);
        app(SanitizeService::class)->getBannedDomains(true);
        app(InstanceService::class)->flushStats();

        return $this->success();
    }

    public function instanceStats(Request $request)
    {
        $res = app(InstanceService::class)->getStats();

        return $this->data($res);
    }

    public function instanceAdvancedStats()
    {
        $res = app(InstanceService::class)->getAdvancedStats();

        return response()->json($res);
    }

    public function manageInstanceToggleBySoftware(Request $request)
    {
        $request->validate([
            'software' => 'required|string',
            'allow_video_posts' => 'required|boolean',
        ]);

        $software = $request->software;
        $allowVideoPosts = (bool) $request->allow_video_posts;
        $updatedCount = Instance::where('software', $software)
            ->update(['allow_video_posts' => $request->allow_video_posts]);

        app(AdminAuditLogService::class)->logInstanceSoftwareUpdateAllowVideoPosts($request->user(), ['software' => $software, 'allow_video_posts' => $allowVideoPosts]);
        app(InstanceService::class)->flushStats();

        return response()->json([
            'data' => [
                'updated_count' => $updatedCount,
            ],
        ]);
    }

    public function manageInstanceToggleByDomains(Request $request)
    {
        $request->validate([
            'domains' => 'required|array',
            'domains.*' => 'string',
            'allow_video_posts' => 'required|boolean',
        ]);

        $domains = array_map(function ($domain) {
            return parse_url($domain, PHP_URL_HOST) ?: $domain;
        }, $request->domains);

        $updatedCount = Instance::whereIn('domain', $domains)
            ->update(['allow_video_posts' => $request->allow_video_posts]);

        app(AdminAuditLogService::class)->logInstanceDomainsUpdateAllowVideoPosts($request->user(), ['domains' => $domains, 'allow_video_posts' => $request->allow_video_posts]);
        app(InstanceService::class)->flushStats();

        return response()->json([
            'data' => [
                'updated_count' => $updatedCount,
            ],
        ]);
    }

    public function instanceCreate(Request $request)
    {
        $validated = $request->validate([
            'domain' => 'required|active_url',
            'is_blocked' => 'sometimes|boolean',
            'allow_video_posts' => 'sometimes|boolean',
            'allow_videos_in_fyf' => 'sometimes|boolean',
            'admin_notes' => 'sometimes|string|nullable|max:2500',
        ]);

        $domain = $request->input('domain');
        $domain = str_replace('https://', '', $domain);
        if (Instance::whereDomain($domain)->exists()) {
            return response()->json(['message' => 'Domain already exists'], 400);
        }

        $instance = new Instance;
        $instance->domain = $domain;
        $instance->is_blocked = $request->boolean('is_blocked', false);
        $instance->federation_state = $instance->is_blocked ? 2 : 5;
        $instance->allow_video_posts = $request->boolean('allow_video_posts', false);
        $instance->allow_videos_in_fyf = $request->boolean('allow_videos_in_fyf', false);
        $instance->admin_notes = $request->input('admin_notes', null);
        $instance->save();

        $versionData = app(NodeinfoCrawlerService::class)->getSoftware($domain);
        if (! $versionData || ! isset($versionData['name'], $versionData['version'])) {
            $instance->version_last_checked_at = now();
            $instance->save();
        } else {
            $instance->software = $versionData['name'];
            $instance->version = $versionData['version'];
            $instance->version_last_checked_at = now();
            $instance->save();
        }

        app(AdminAuditLogService::class)->logInstanceDomainAdded($request->user(), $instance);
        app(SanitizeService::class)->getBannedDomains(true);
        app(InstanceService::class)->flushStats();

        return $this->success();
    }

    public function instanceBulkCreate(Request $request)
    {
        $validated = $request->validate([
            'domains' => 'required|array|min:1|max:100',
            'domains.*' => 'url',
            'is_blocked' => 'sometimes|boolean',
            'allow_video_posts' => 'sometimes|boolean',
            'allow_videos_in_fyf' => 'sometimes|boolean',
            'admin_notes' => 'sometimes|string|nullable|max:2500',
        ]);

        $domains = $request->input('domains');

        $isBlocked = $request->boolean('is_blocked', false);
        $allowVideoPosts = $request->boolean('allow_video_posts', false);
        $allowVideosInFyf = $request->boolean('allow_videos_in_fyf', false);
        $adminNotes = $request->input('admin_notes', null);

        $createdInstances = [];
        $errors = [];

        DB::beginTransaction();

        try {
            foreach ($domains as $domain) {
                try {
                    $cleanDomain = str_replace(['https://', 'http://'], '', $domain);

                    $existingInstance = Instance::where('domain', $cleanDomain)->first();
                    if ($existingInstance) {
                        $errors[] = "Instance {$cleanDomain} already exists";

                        continue;
                    }

                    $instance = new Instance;
                    $instance->domain = $cleanDomain;
                    $instance->is_blocked = $isBlocked;
                    $instance->federation_state = $isBlocked ? 2 : 5;
                    $instance->allow_video_posts = $allowVideoPosts;
                    $instance->allow_videos_in_fyf = $allowVideosInFyf;
                    $instance->admin_notes = $adminNotes;
                    $instance->save();

                    $createdInstances[] = $instance;

                    app(AdminAuditLogService::class)->logInstanceDomainAdded($request->user(), $instance);

                    FetchInstanceNodeinfo::dispatch($instance);

                } catch (\Exception $e) {
                    $errors[] = "Failed to create instance for {$domain}: ".$e->getMessage();
                    \Log::error("Bulk instance creation error for {$domain}", [
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                    ]);
                }
            }

            DB::commit();

            app(SanitizeService::class)->getBannedDomains(true);

            $response = [
                'success' => true,
                'created_count' => count($createdInstances),
                'total_count' => count($domains),
            ];

            if (! empty($errors)) {
                $response['errors'] = $errors;
                $response['error_count'] = count($errors);
            }

            return response()->json($response);

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Bulk instance creation transaction failed', [
                'error' => $e->getMessage(),
                'domains' => $domains,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Bulk instance creation failed',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function versionCheck(VersionCheckService $versionCheck)
    {
        return response()->json($versionCheck->checkForUpdates());
    }

    public function getAdminInvites(Request $request)
    {
        $search = $request->query('q');
        $sort = $request->query('sort');

        $query = AdminInvite::query();
        if (! in_array($sort, ['deleted', 'expired'])) {
            $query = AdminInvite::query()->where('is_active', 1)->unexpired();
        } elseif (in_array($sort, ['deleted'])) {
            $query = AdminInvite::withTrashed()->whereNotNull('deleted_at');
        } elseif (in_array($sort, ['expired'])) {
            $query = AdminInvite::expired();
        }

        if (! empty($search)) {
            if (str_starts_with($search, 'message:')) {
                $message = trim(substr($search, 8));
                if (! empty($message)) {
                    $query->where('message', 'like', '%'.$message.'%');
                }
            } elseif (str_starts_with($search, 'note:')) {
                $note = trim(substr($search, 5));
                if (! empty($note)) {
                    $query->where('admin_note', 'like', '%'.$note.'%');
                }
            } else {
                $query->where('title', 'like', '%'.$search.'%');
            }
        }

        $query = $this->applySorting($query, $sort);

        $invites = $query->cursorPaginate(10)->withQueryString();

        return AdminInviteResource::collection($invites);
    }

    public function storeAdminInvite(StoreAdminInviteRequest $request)
    {
        $inviteKey = 'a_'.Str::uuid7().Str::random(7);
        $validated = $request->safe()->merge(['invite_key' => $inviteKey, 'invited_by' => $request->user()->profile_id]);
        $invite = AdminInvite::create($validated->all());

        return new AdminInviteResource($invite);
    }

    public function showAdminInvite(Request $request, $id)
    {
        $invite = AdminInvite::withTrashed()->findOrFail($id);

        return new AdminInviteResource($invite);
    }

    public function updateAdminInvite(StoreAdminInviteRequest $request, $id)
    {
        $invite = AdminInvite::findOrFail($id);
        $validated = $request->safe();
        $invite->update($validated->all());

        return new AdminInviteResource($invite->fresh());
    }

    public function deleteAdminInvite(Request $request, $id)
    {
        $invite = AdminInvite::findOrFail($id);
        $invite->delete();

        return $this->success();
    }

    private function applySorting($query, $sort)
    {
        if ($sort === 'comment_replies.is_hidden') {
            return $query->where('comment_replies.is_hidden', true);
        }

        if ($sort === 'comments.is_hidden') {
            return $query->where('comments.is_hidden', true);
        }

        if ($sort === 'allow_video_posts') {
            return $query->where('allow_video_posts', 1);
        }

        if ($sort === 'unprocessed') {
            return $query->where('status', 1);
        }

        if (in_array($sort, ['deleted', 'expired'])) {
            return $query->orderBy('deleted_at', 'desc');
        }

        $sortOptions = [
            'username_asc' => ['username', 'asc'],
            'username_desc' => ['username', 'desc'],
            'video_count_desc' => ['video_count', 'desc'],
            'user_count_desc' => ['user_count', 'desc'],
            'comment_count_desc' => ['comment_count', 'desc'],
            'reply_count_desc' => ['reply_count', 'desc'],
            'follower_count_desc' => ['follower_count', 'desc'],
            'following_count_desc' => ['following_count', 'desc'],
            'followers_asc' => ['followers', 'asc'],
            'followers_desc' => ['followers', 'desc'],
            'likes_desc' => ['likes', 'desc'],
            'comments_desc' => ['comments', 'desc'],
            'created_at_asc' => ['created_at', 'asc'],
            'created_at_desc' => ['created_at', 'desc'],
            'updated_at_asc' => ['updated_at', 'asc'],
            'updated_at_desc' => ['updated_at', 'desc'],
            'replies_desc' => ['replies', 'desc'],
            'popular' => ['followers', 'desc'],
            'newest' => ['created_at', 'desc'],
            'oldest' => ['created_at', 'asc'],
            'count_desc' => ['count', 'desc'],
            'domain_asc' => ['domain', 'asc'],
            'domain_desc' => ['domain', 'desc'],
        ];

        if (str_starts_with($sort, 'is_')) {
            $query->where($sort, true);

            return $query->orderBy('id', 'desc');
        }

        if (str_starts_with($sort, 'can_')) {
            $query->where($sort, true);

            return $query->orderBy('id', 'desc');
        }

        if ($sort && isset($sortOptions[$sort])) {
            [$column, $direction] = $sortOptions[$sort];
            $query->orderBy($column, $direction)->orderByDesc('id');
        } else {
            $query->orderBy('id', 'desc');
        }

        return $query;
    }

    private function applySortingJoin($query, $sort, $tableName = null)
    {
        $columnTableMap = [
            'username' => 'profiles',
            'followers' => 'profiles',
            'video_count' => 'profiles',
            'likes' => 'videos',
            'comments' => 'videos',
            'created_at' => $tableName ?? 'videos',
            'updated_at' => $tableName ?? 'videos',
            'count' => $tableName ?? 'videos',
            'id' => $tableName ?? 'videos',
        ];

        $sortOptions = [
            'username_asc' => ['username', 'asc'],
            'username_desc' => ['username', 'desc'],
            'video_count_desc' => ['video_count', 'desc'],
            'followers_asc' => ['followers', 'asc'],
            'followers_desc' => ['followers', 'desc'],
            'likes_desc' => ['likes', 'desc'],
            'comments_desc' => ['comments', 'desc'],
            'created_at_asc' => ['created_at', 'asc'],
            'created_at_desc' => ['created_at', 'desc'],
            'updated_at_asc' => ['updated_at', 'asc'],
            'updated_at_desc' => ['updated_at', 'desc'],
            'popular' => ['followers', 'desc'],
            'newest' => ['created_at', 'desc'],
            'oldest' => ['created_at', 'asc'],
            'count_desc' => ['count', 'desc'],
        ];

        if ($sort && $sort == 'suspended') {
            $query->where('status', 6)->orderByDesc('updated_at');

            return $query;
        } elseif ($sort && $sort == 'disabled') {
            $query->where('status', 7)->orderByDesc('updated_at');

            return $query;
        } elseif ($sort && $sort == 'deleted') {
            $query->where('status', 8)->orderByDesc('updated_at');

            return $query;
        } elseif ($sort && isset($sortOptions[$sort])) {
            [$column, $direction] = $sortOptions[$sort];

            $tablePrefix = $columnTableMap[$column].'.';
            $qualifiedColumn = $tablePrefix.$column;

            $query->orderBy($qualifiedColumn, $direction);

            $query->orderByDesc(($tableName ?? 'videos').'.id');
        } else {
            if (request()->query('q')) {
                $query->orderBy('profiles.followers', 'desc')
                    ->orderBy(($tableName ?? 'videos').'.id', 'desc');
            } else {
                $query->orderBy(($tableName ?? 'videos').'.id', 'desc');
            }
        }

        return $query;
    }

    private function queryHasJoin($query, $table)
    {
        $joins = $query->getQuery()->joins ?? [];
        foreach ($joins as $join) {
            if ($join->table === $table) {
                return true;
            }
        }

        return false;
    }
}
