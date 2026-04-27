<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Traits\ApiHelpers;
use App\Http\Controllers\Controller;
use App\Http\Requests\AdminResetProfilePasswordRequest;
use App\Http\Requests\AdminSendProfileEmailRequest;
use App\Http\Requests\StoreAdminInviteRequest;
use App\Http\Resources\AdminAuditLogResource;
use App\Http\Resources\AdminBlockedTermResource;
use App\Http\Resources\AdminHashtagResource;
use App\Http\Resources\AdminInstanceResource;
use App\Http\Resources\AdminInviteResource;
use App\Http\Resources\AdminProfileResource;
use App\Http\Resources\AdminStarterKitHistoryResource;
use App\Http\Resources\AdminStarterKitResource;
use App\Http\Resources\AdminUserAuditLogResource;
use App\Http\Resources\CommentReplyResource;
use App\Http\Resources\CommentResource;
use App\Http\Resources\ProfileResource;
use App\Http\Resources\ReportResource;
use App\Http\Resources\StarterKitPendingChangeResource;
use App\Http\Resources\VideoResource;
use App\Jobs\Admin\AdminApproveStarterKitJob;
use App\Jobs\Federation\FetchInstanceNodeinfo;
use App\Mail\AdminMessageMail;
use App\Mail\AdminPasswordResetMail;
use App\Models\AdminAuditLog;
use App\Models\AdminInvite;
use App\Models\BlockedTerm;
use App\Models\Comment;
use App\Models\CommentReply;
use App\Models\Follower;
use App\Models\Hashtag;
use App\Models\Instance;
use App\Models\Profile;
use App\Models\Report;
use App\Models\StarterKit;
use App\Models\StarterKitAccount;
use App\Models\StarterKitPendingChange;
use App\Models\User;
use App\Models\UserAuditLog;
use App\Models\Video;
use App\Services\AccountService;
use App\Services\AccountSuggestionService;
use App\Services\AdminAuditLogService;
use App\Services\AdminDashboardService;
use App\Services\AvatarService;
use App\Services\ExploreService;
use App\Services\InstanceService;
use App\Services\NodeinfoCrawlerService;
use App\Services\PrivateMediaTokenService;
use App\Services\ProfanityFilterService;
use App\Services\PushTokenCacheService;
use App\Services\SanitizeService;
use App\Services\StarterKitPendingChangeService;
use App\Services\StarterKitService;
use App\Services\VersionCheckService;
use App\Services\VideoService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

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
            $res['user_id'] = $user->id;
            $res['has_atom'] = (bool) $user->has_atom;
            $res['atom_url'] = $user->atomUrl();
            $res['has_push'] = (bool) $user->push_token;
            $res['has_2fa'] = (bool) $user->has_2fa && $user->two_factor_secret;
            $res['last_ip'] = $user->is_admin ? null : $user->last_ip;
            $res['push_platform'] = $user->push_token_platform;
            if ($user->last_active_at) {
                $res['last_active_at'] = $user->last_active_at->format('c');
            }
            $res['domain'] = parse_url(config('app.url'), PHP_URL_HOST);
        } else {
            $res['domain'] = parse_url($profile->uri, PHP_URL_HOST);
        }
        $res['starter_kits_created_count'] = StarterKit::whereProfileId($profile->id)->count();
        $res['starter_kits_included_in_count'] = StarterKitAccount::whereProfileId($profile->id)->approved()->count();
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
        $res['can_report'] = (bool) $profile->can_report;
        $res['can_create_starter_kits'] = (bool) $profile->can_create_starter_kits;
        $res['can_use_starter_kits'] = (bool) $profile->can_use_starter_kits;
        $res['can_report'] = (bool) $profile->can_report;
        $res['updated_at'] = $profile->updated_at;

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
            'can_report' => 'sometimes|boolean',
            'can_create_starter_kits' => 'sometimes|boolean',
            'can_use_starter_kits' => 'sometimes|boolean',
        ]);

        $userValidated = $request->validate([
            'can_upload' => 'sometimes|boolean',
            'can_follow' => 'sometimes|boolean',
            'can_comment' => 'sometimes|boolean',
            'can_like' => 'sometimes|boolean',
            'can_report' => 'sometimes|boolean',
            'can_create_starter_kits' => 'sometimes|boolean',
            'can_use_starter_kits' => 'sometimes|boolean',
        ]);

        $profile = Profile::find($id);

        if (! $profile) {
            return $this->error('Ooops!');
        }

        $oldValues = $profile->only(['can_upload', 'can_follow', 'can_comment', 'can_like', 'can_share', 'can_report', 'can_create_starter_kits', 'can_use_starter_kits']);

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

    public function profileAuditLog(Request $request, $id)
    {
        $profile = Profile::findOrFail($id);

        $auditLogs = AdminAuditLog::whereActivityType('App\Models\Profile')->whereActivityId($profile->id)->orderByDesc('id')->cursorPaginate(5);

        return AdminAuditLogResource::collection($auditLogs);
    }

    public function profileUserAuditLog(Request $request, $id)
    {
        $user = User::whereProfileId($id)->firstOrFail();

        $auditLogs = UserAuditLog::whereUserId($user->id)->orderByDesc('id')->cursorPaginate(5);

        return AdminUserAuditLogResource::collection($auditLogs);
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
            app(PushTokenCacheService::class)->remove((string) $profile->id);
            DB::table('oauth_access_tokens')->where('user_id', $profile->user_id)->delete();
            DB::table('oauth_auth_codes')->where('user_id', $profile->user_id)->delete();
            DB::table('sessions')->where('user_id', $profile->user_id)->delete();
            $profile->user->update(['status' => 6, 'push_token' => null, 'push_token_platform' => null, 'push_token_verified_at' => null]);
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

    public function profileToggleEmailVerify(Request $request, $id)
    {
        $profile = Profile::whereNotNull('user_id')->find($id);

        if (! $profile) {
            return $this->error('Ooops!');
        }

        $user = $profile->user;

        if ($user->email_verified_at) {
            $profile->user->update(['email_verified_at' => null]);
            app(AdminAuditLogService::class)->logProfileUnverifyEmail($request->user(), $profile);
        } else {
            $profile->user->update(['email_verified_at' => now()]);
            app(AdminAuditLogService::class)->logProfileVerifyEmail($request->user(), $profile);
        }

        AccountService::del($profile->id);

        return $this->success();
    }

    public function profileDeleteAvatar(Request $request, $id)
    {
        $profile = Profile::find($id);

        AvatarService::deleteAvatar($profile);

        app(AdminAuditLogService::class)->logProfileDeleteAvatar($request->user(), $profile);

        return $this->success();
    }

    public function profileDisableTwoFactorAuth(Request $request, $id)
    {
        $profile = Profile::whereNotNull('user_id')->find($id);

        $user = User::findOrFail($profile->user_id);

        if ($user->has_2fa) {
            $user->has_2fa = 0;
            $user->two_factor_secret = null;
            $user->two_factor_backups = null;
            $user->save();
            app(AdminAuditLogService::class)->logProfileDisableTwoFactorAuth($request->user(), $profile);
        }

        return $this->success();
    }

    public function profileAdminNoteUpdate(Request $request, $id)
    {
        $validated = $request->validate([
            'admin_notes' => 'sometimes|nullable|string',
        ]);

        $profile = Profile::find($id);

        if (! $profile) {
            return $this->error('Ooops!');
        }

        $oldValues = $profile->only(['admin_notes']);

        $profile->admin_notes = $request->input('admin_notes');
        $profile->save();

        app(AdminAuditLogService::class)->logProfileAdminNoteUpdate($request->user(), $profile, ['old' => $oldValues, 'new' => $validated]);

        return $this->success();
    }

    public function profileAdminSendEmail(AdminSendProfileEmailRequest $request, $id)
    {
        $profile = Profile::with('user')->findOrFail($id);

        abort_if(! $profile->local || ! $profile->user, 404, 'Remote profile cannot be emailed');
        abort_if(! $profile->user->email, 422, 'User has no email address on file');
        abort_if(! $profile->user->email_verified_at, 422, 'User email is not verified');

        $data = $request->validated();
        $ccAdmin = (bool) ($data['cc_admin'] ?? false);
        $logAsAudit = (bool) ($data['log_as_audit'] ?? true);

        $mailer = Mail::to($profile->user->email);

        if ($ccAdmin && $adminList = config('loops.admin_mails.to')) {
            $adminList = explode(',', $adminList);
            $mailer->bcc($adminList);
        }

        $mailer->queue(new AdminMessageMail(
            recipient: $profile->user,
            subjectLine: $data['subject'],
            body: $data['message'],
            templateId: $data['template_id'] ?? null,
            sentByAdminId: $request->user()->id,
        ));

        if ($logAsAudit) {
            app(AdminAuditLogService::class)->logProfileAdminSendEmail($request->user(), $profile, [
                'subject' => $data['subject'],
                'template_id' => $data['template_id'] ?? null,
                'cc_admin' => $ccAdmin,
                'message_body' => $data['message'],
                'message_length' => mb_strlen($data['message']),
            ]);
        }

        return response()->json([
            'message' => 'Email queued for delivery',
            'recipient' => $profile->user->email,
        ]);
    }

    public function profileAdminResetPassword(AdminResetProfilePasswordRequest $request, $id)
    {
        $profile = Profile::with('user')->findOrFail($id);

        abort_if(! $profile->local || ! $profile->user, 404, 'Only local profiles can be reset');
        abort_if($profile->user->is_admin, 403, 'Admin passwords cannot be reset through this endpoint');
        abort_if($profile->status !== 1, 422, 'Cannot reset a non-active profile');

        $data = $request->validated();
        $sendEmail = (bool) ($data['send_email'] ?? false);
        $forceChange = (bool) ($data['force_change'] ?? false);
        $revokeSessions = (bool) ($data['revoke_sessions'] ?? false);

        $canEmail = $profile->user->email && $profile->user->email_verified_at;
        $shouldEmail = $sendEmail && $canEmail;

        DB::transaction(function () use ($profile, $data, $forceChange, $revokeSessions, $request) {
            $profile->user->forceFill([
                'password' => Hash::make($data['password']),
                'must_change_password' => $forceChange,
                'remember_token' => null,
            ])->save();

            if ($revokeSessions) {
                $this->revokeAllSessions($profile->user);
            }

            app(AdminAuditLogService::class)->logProfileForcePasswordReset($request->user(), $profile);
        });

        if ($shouldEmail) {
            Mail::to($profile->user->email)->queue(new AdminPasswordResetMail(
                recipient: $profile->user,
                password: $data['password'],
                forceChange: $forceChange,
                sentByAdminId: $request->user()->id,
            ));
        }

        return response()->json([
            'message' => 'Password reset successfully',
            'email_sent' => $shouldEmail,
            'email_skipped_reason' => ! $sendEmail
                ? null
                : ($canEmail ? null : 'unverified_email'),
        ]);
    }

    protected function revokeAllSessions($user): void
    {
        if (config('session.driver') === 'database') {
            DB::table(config('session.table', 'sessions'))
                ->where('user_id', $user->id)
                ->delete();
        }

        if (method_exists($user, 'tokens')) {
            $tokenIds = $user->tokens()->pluck('id');

            $user->tokens()->update(['revoked' => true]);

            if ($tokenIds->isNotEmpty() && class_exists(\Laravel\Passport\RefreshToken::class)) {
                \Laravel\Passport\RefreshToken::query()
                    ->whereIn('access_token_id', $tokenIds)
                    ->update(['revoked' => true]);
            }
        }

        if ($user->push_token) {
            $user->update([
                'push_token' => null,
                'push_token_verified_at' => null,
                'push_token_platform' => null,
            ]);
        }
    }

    public function reportCount(Request $request)
    {
        $res = app(AdminDashboardService::class)->getReportsCount();

        return $this->data($res);
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
        app(AdminDashboardService::class)->getReportsCount(true);

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
        app(AdminDashboardService::class)->getReportsCount(true);

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
        app(AdminDashboardService::class)->getReportsCount(true);

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
        app(AdminDashboardService::class)->getReportsCount(true);

        return $this->success();
    }

    public function reportDismiss(Request $request, $id)
    {
        $report = Report::where('admin_seen', false)->findOrFail($id);
        $report->admin_seen = true;
        $report->save();

        app(AdminAuditLogService::class)->logReportDismiss($request->user(), $report, ['profile_id' => $report->reporter_profile_id]);
        app(AdminDashboardService::class)->getReportsCount(true);

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

        app(AdminDashboardService::class)->getReportsCount(true);

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
        app(AdminDashboardService::class)->getReportsCount(true);

        return $this->success();
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

        app(AdminDashboardService::class)->getReportsCount(true);

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

        app(AdminDashboardService::class)->getReportsCount(true);

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

        $query = $query->with('mediaAttachments');

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
        $query = Comment::with('mediaAttachments')->findOrFail($id);

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

    public function blockedTerms(Request $request)
    {
        $query = BlockedTerm::query();

        $search = $request->filled('q') ? $request->query('q') : null;

        $sort = $request->query('sort') ?? 'newest';

        if ($type = $request->input('type')) {
            $query->where('type', $type);
        }

        if ($search) {
            $query->where('term', 'like', "%{$search}%");
        }

        $query = $this->applySorting($query, $sort);

        $res = $query->cursorPaginate(25)->withQueryString();

        return AdminBlockedTermResource::collection($res);
    }

    public function blockedTermsStore(Request $request)
    {
        $data = $request->validate([
            'term' => ['required', 'string', 'max:120', 'unique:blocked_terms,term'],
            'type' => ['required', 'in:block,allow'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $data['term'] = mb_strtolower(trim($data['term']));
        $data['created_by'] = $request->user()->id;

        $term = BlockedTerm::create($data);

        app(AdminAuditLogService::class)->logBlockedTermCreate($request->user(), $term);
        BlockedTerm::flushCache();

        return new AdminBlockedTermResource($term);
    }

    public function blockedTermsUpdate(Request $request, BlockedTerm $blockedTerm)
    {
        $data = $request->validate([
            'term' => ['sometimes', 'string', 'max:120', 'unique:blocked_terms,term,'.$blockedTerm->id],
            'type' => ['sometimes', 'in:block,allow'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        if (isset($data['term'])) {
            $data['term'] = mb_strtolower(trim($data['term']));
        }
        $old = $blockedTerm->only(['term', 'type', 'note']);
        $blockedTerm->update($data);
        $blockedTerm->refresh();

        $changes = ['old' => $old, 'new' => $blockedTerm->only(['term', 'type', 'note'])];

        app(AdminAuditLogService::class)->logBlockedTermUpdate($request->user(), $blockedTerm, $changes);

        BlockedTerm::flushCache();

        return new AdminBlockedTermResource($blockedTerm);
    }

    public function blockedTermsDestroy(Request $request, BlockedTerm $blockedTerm)
    {
        $changes = $blockedTerm->only(['id', 'term', 'type', 'note', 'created_by', 'created_at']);
        app(AdminAuditLogService::class)->logBlockedTermDelete($request->user(), $blockedTerm, $changes);

        $blockedTerm->delete();
        BlockedTerm::flushCache();

        return response()->noContent();
    }

    public function blockedTermsTest(Request $request, ProfanityFilterService $filter)
    {
        $data = $request->validate([
            'query' => ['required', 'string', 'max:500'],
        ]);

        return response()->json($filter->inspect($data['query']));
    }

    public function blockedTermsBulkFlushCache(Request $request)
    {
        BlockedTerm::flushCache();

        return response()->noContent();
    }

    public function blockedTermsCounts(Request $request)
    {
        $counts = Cache::remember('admin:blocked-terms:counts', now()->addHours(15), function () {
            $byType = BlockedTerm::selectRaw('type, COUNT(*) as count')
                ->groupBy('type')
                ->pluck('count', 'type');

            return [
                'total' => (int) $byType->sum(),
                'block' => (int) ($byType['block'] ?? 0),
                'allow' => (int) ($byType['allow'] ?? 0),
            ];
        });

        return response()->json($counts);
    }

    public function blockedTermsExport(Request $request)
    {
        $validated = $request->validate([
            'type' => 'required|in:all,block,allow',
            'format' => 'required|in:list,csv,json',
        ]);

        $stamp = now()->format('Y-m-d');

        $ext = match ($validated['format']) {
            'list' => 'txt',
            'csv' => 'csv',
            'json' => 'json',
            default => 'txt',
        };

        $filename = "blocked-terms-{$validated['type']}-{$stamp}.{$ext}";

        return match ($validated['format']) {
            'list' => $this->exportList($validated['type'], $filename),
            'csv' => $this->exportCsv($validated['type'], $filename),
            'json' => $this->exportJson($validated['type'], $filename),
            default => [],
        };
    }

    public function blockedTermsDeleteAll(Request $request)
    {
        $validated = $request->validate([
            'type' => 'required|in:all,block,allow',
        ]);

        $query = BlockedTerm::query();
        if ($validated['type'] !== 'all') {
            $query->where('type', $validated['type']);
        }

        app(AdminAuditLogService::class)->logBlockedTermDeleteAll($request->user(), ['total' => $query->count()]);

        $deleted = $query->delete();

        BlockedTerm::flushCache();

        return response()->json(['deleted' => (int) $deleted]);
    }

    public function blockedTermsBulkImport(Request $request)
    {
        $data = $request->validate([
            'terms' => ['required', 'array', 'max:1000'],
            'terms.*' => ['required', 'string', 'max:120'],
            'type' => ['required', 'in:block,allow'],
            'note' => ['sometimes', 'nullable', 'string', 'max:500'],
        ]);

        $now = now();
        $userId = $request->user()->id;

        $rows = collect($data['terms'])
            ->map(fn ($t) => mb_strtolower(trim($t)))
            ->filter()
            ->unique()
            ->map(fn ($term) => [
                'term' => $term,
                'type' => $data['type'],
                'note' => $data['note'],
                'created_by' => $userId,
                'created_at' => $now,
                'updated_at' => $now,
            ])
            ->all();

        BlockedTerm::insertOrIgnore($rows);
        BlockedTerm::flushCache();

        app(AdminAuditLogService::class)->logBlockedTermBulkImport($request->user(), ['total' => count($rows)]);

        return response()->json(['inserted' => count($rows)]);
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

    public function starterKits(Request $request)
    {
        $search = $request->query('q');
        $sort = $request->query('sort');
        $local = $request->query('local');

        $query = StarterKit::query();

        $query->select('starter_kits.*');

        if ($local == true) {
            $query->where('starter_kits.is_local', true);
        }

        if (! empty($search)) {
            if (str_starts_with($search, 'user:')) {
                $user = trim(substr($search, 5));
                if (! empty($user)) {
                    $query->join('profiles', 'starter_kits.profile_id', '=', 'profiles.id')
                        ->where('profiles.username', 'like', '%'.$user.'%');
                }
            } else {
                $query->where('starter_kits.title', 'like', '%'.$search.'%')->orWhere('description', 'like', '%'.$search.'%');
            }
        }

        $query = $this->applySorting($query, $sort);

        $kits = $query->cursorPaginate(10)->withQueryString();

        return AdminStarterKitResource::collection($kits);
    }

    public function starterKitsShow(Request $request, $id)
    {
        $kit = StarterKit::findOrFail($id);

        return (new AdminStarterKitResource($kit))->response()->setEncodingOptions(JSON_UNESCAPED_SLASHES);
    }

    public function starterKitHistory(Request $request, $id)
    {
        $starterKit = StarterKit::findOrFail($id);

        $logs = AdminAuditLog::where('activity_type', StarterKit::class)->where('activity_id', $starterKit->id)->orderByDesc('id')->cursorPaginate(10);

        return AdminStarterKitHistoryResource::collection($logs);
    }

    public function starterKitsUpdate(Request $request, $id)
    {
        $starterKit = StarterKit::findOrFail($id);

        $validated = $request->validate([
            'title' => 'sometimes|string|max:80',
            'description' => 'nullable|string|max:500',
            'hashtags' => 'nullable|array|max:10',
            'hashtags.*' => 'string|max:20',
            'is_discoverable' => 'boolean',
            'is_sensitive' => 'boolean',
        ]);

        if (isset($validated['title'])) {
            $validated['slug'] = Str::slug($validated['title']);
        }

        $hashtags = $validated['hashtags'] ?? null;
        unset($validated['hashtags']);

        $watchedFields = ['title', 'description', 'is_discoverable', 'is_sensitive'];
        $before = collect($watchedFields)
            ->mapWithKeys(fn ($f) => [$f => $starterKit->$f])
            ->all();

        $oldHashtags = $starterKit->hashtags()->pluck('name')->all();

        $starterKit->update($validated);

        if ($request->has('hashtags')) {
            (new StarterKitController)->syncHashtags($starterKit, $hashtags ?? []);
        }

        $fresh = $starterKit->fresh();
        $newHashtags = $fresh->hashtags()->pluck('name')->all();

        $old = [];
        $new = [];

        foreach ($watchedFields as $field) {
            if (array_key_exists($field, $validated) && $before[$field] !== $fresh->$field) {
                $old[$field] = $before[$field];
                $new[$field] = $fresh->$field;
            }
        }

        if ($oldHashtags !== $newHashtags) {
            $old['hashtags'] = $oldHashtags;
            $new['hashtags'] = $newHashtags;
        }

        if (! empty($new)) {
            (new AdminAuditLogService)->logStarterKitUpdate(
                $request->user()->profile_id,
                $starterKit,
                ['old' => $old, 'new' => $new]
            );
        }

        app(StarterKitService::class)->forget($starterKit->id);
        app(AdminDashboardService::class)->getReportsCount(true);

        return new AdminStarterKitResource($fresh);
    }

    public function starterKitsModerate(Request $request, $id)
    {
        $starterKit = StarterKit::findOrFail($id);

        $validated = $request->validate([
            'action' => 'required|string|in:approve,suspend,delete,pending',
        ]);

        $config = app(StarterKitService::class)->getConfig();
        $adminApproval = data_get($config, 'requires_admin_approval', true);

        $action = $validated['action'];

        if ($action === 'approve') {
            if (! $starterKit->admin_approved_at && $adminApproval) {
                AdminApproveStarterKitJob::dispatch($starterKit);
            } else {
                $starterKit->status = 10;
                $starterKit->admin_approved_at = now();
                $starterKit->save();
            }
            (new AdminAuditLogService)->logStarterKitApproved($request->user()->profile_id, $starterKit->fresh(), null);
            app(StarterKitService::class)->applyBundledPendingChanges($starterKit->fresh());
        } elseif ($action === 'suspend') {
            $starterKit->status = 5;
            $starterKit->admin_approved_at = null;
            $starterKit->save();
            (new AdminAuditLogService)->logStarterKitSuspend($request->user()->profile_id, $starterKit, null);
        } elseif ($action === 'pending') {
            $starterKit->status = 2;
            $starterKit->admin_approved_at = null;
            $starterKit->save();
            (new AdminAuditLogService)->logStarterKitPending($request->user()->profile_id, $starterKit, null);
        } elseif ($action === 'delete') {
            (new AdminAuditLogService)->logStarterKitDelete($request->user()->profile_id, $starterKit, ['title' => $starterKit->title, 'profile_id' => $starterKit->profile_id]);
            app(StarterKitService::class)->forget($starterKit->id);
            $starterKit->delete();
            app(AdminDashboardService::class)->getReportsCount(true);

            return $this->success();
        }

        app(StarterKitService::class)->forget($starterKit->id);
        app(AdminDashboardService::class)->getReportsCount(true);

        return new AdminStarterKitResource($starterKit->fresh());
    }

    public function starterKitsModerateMedia(Request $request, $id)
    {
        $starterKit = StarterKit::findOrFail($id);

        $validated = $request->validate([
            'type' => 'required|string|in:header,icon',
        ]);

        switch ($validated['type']) {
            case 'header':
                app(StarterKitService::class)->unlinkHeaderMedia($starterKit);
                $starterKit->update(['header_path' => null, 'header_url' => null]);
                (new AdminAuditLogService)->logStarterKitMediaDelete($request->user()->profile_id, $starterKit->fresh(), ['header_path' => null, 'header_url' => null]);
                break;

            case 'icon':
                app(StarterKitService::class)->unlinkIconMedia($starterKit);
                $starterKit->update(['icon_path' => null, 'icon_url' => null]);
                (new AdminAuditLogService)->logStarterKitMediaDelete($request->user()->profile_id, $starterKit->fresh(), ['icon_path' => null, 'icon_url' => null]);
                break;
        }

        app(StarterKitService::class)->forget($starterKit->id);

        return new AdminStarterKitResource($starterKit->fresh());
    }

    public function starterKitsReview(Request $request)
    {
        $request->validate([
            'status' => ['sometimes', Rule::in(['pending', 'in_review', 'applied', 'rejected'])],
        ]);

        $changesets = StarterKitPendingChange::query()
            ->with(['starterKit:id,title,icon_url,header_url', 'profile:id,username,avatar'])
            ->where('bundled_with_kit_review', false)
            ->when(
                $request->input('status', 'pending'),
                fn ($q, $status) => $status === 'pending'
                    ? $q->whereIn('status', ['pending', 'in_review'])
                    : $q->where('status', $status)
            )
            ->latest()
            ->paginate(5);

        app(AdminDashboardService::class)->getReportsCount(true);

        return StarterKitPendingChangeResource::collection($changesets);
    }

    public function starterKitsReviewShow(StarterKitPendingChange $changeset)
    {
        $changeset->load([
            'starterKit:id,title,description,icon_url,header_url',
            'profile:id,username,avatar',
            'reviewer:id,username',
        ]);

        return new StarterKitPendingChangeResource($changeset);
    }

    public function starterKitsReviewApproveField(Request $request, StarterKitPendingChange $changeset, string $field)
    {
        $this->validateField($changeset, $field);
        $this->validateFieldIsReviewable($changeset, $field);

        $starterKit = StarterKit::find($changeset->starter_kit_id);
        app(StarterKitPendingChangeService::class)->approveField($changeset, $field, $request->user()->profile_id);
        (new AdminAuditLogService)->logStarterKitApproveFieldUpdate($request->user()->profile_id, $starterKit->fresh(), [$field]);

        app(AdminDashboardService::class)->getReportsCount(true);
        app(PrivateMediaTokenService::class)->revokeFor($changeset);

        return response()->json([
            'field' => $field,
            'status' => 'approved',
            'applied' => $changeset->fresh()->status === 'applied',
        ]);
    }

    public function starterKitsReviewRejectField(Request $request, StarterKitPendingChange $changeset, string $field)
    {
        $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $this->validateField($changeset, $field);
        $this->validateFieldIsReviewable($changeset, $field);

        $starterKit = StarterKit::find($changeset->starter_kit_id);

        app(StarterKitPendingChangeService::class)->rejectField(
            $changeset,
            $field,
            $request->user()->profile_id,
            $request->input('reason')
        );
        (new AdminAuditLogService)->logStarterKitRejectFieldUpdate($request->user()->profile_id, $starterKit->fresh(), [$field]);

        app(AdminDashboardService::class)->getReportsCount(true);
        app(PrivateMediaTokenService::class)->revokeFor($changeset);

        return response()->json([
            'field' => $field,
            'status' => 'rejected',
            'applied' => $changeset->fresh()->status === 'applied',
        ]);
    }

    private function validateField(StarterKitPendingChange $changeset, string $field): void
    {
        abort_unless(
            array_key_exists($field, $changeset->changeset),
            422,
            "Field [{$field}] does not exist in this changeset."
        );
    }

    private function validateFieldIsReviewable(StarterKitPendingChange $changeset, string $field): void
    {
        abort_unless(
            $changeset->changeset[$field]['status'] === 'pending',
            422,
            "Field [{$field}] has already been reviewed."
        );

        abort_if(
            $changeset->status === 'applied' && $changeset->allFieldsReviewed(),
            422,
            'Changeset has already been fully applied.'
        );
    }

    private function applySorting($query, $sort)
    {
        if ($sort === 'starter_kits.awaiting_review') {
            return $query->where('status', 0);
        }

        if ($sort === 'starter_kits.approved') {
            return $query->where('status', 10);
        }

        if ($sort === 'starter_kits.suspended') {
            return $query->where('status', 5);
        }

        if ($sort === 'starter_kits.disabled') {
            return $query->where('status', 2);
        }

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
            'title_desc' => ['title', 'desc'],
            'title_asc' => ['title', 'asc'],
            'popular' => ['followers', 'desc'],
            'newest' => ['created_at', 'desc'],
            'oldest' => ['created_at', 'asc'],
            'count_desc' => ['count', 'desc'],
            'domain_asc' => ['domain', 'asc'],
            'domain_desc' => ['domain', 'desc'],
            'term_asc' => ['term', 'asc'],
            'term_desc' => ['term', 'desc'],
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

    protected function exportList(string $type, string $filename): StreamedResponse
    {
        return response()->streamDownload(function () use ($type) {
            $handle = fopen('php://output', 'w');

            $query = BlockedTerm::query()->orderBy('id');
            if ($type !== 'all') {
                $query->where('type', $type);
            }

            $query->chunk(1000, function ($terms) use ($handle) {
                foreach ($terms as $term) {
                    fwrite($handle, $term->term."\n");
                }
            });

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/plain; charset=UTF-8',
        ]);
    }

    protected function exportCsv(string $type, string $filename): StreamedResponse
    {
        return response()->streamDownload(function () use ($type) {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, ['term', 'type', 'note', 'created_at']);

            $query = BlockedTerm::query()->orderBy('id');
            if ($type !== 'all') {
                $query->where('type', $type);
            }

            $query->chunk(1000, function ($terms) use ($handle) {
                foreach ($terms as $term) {
                    fputcsv($handle, [
                        $term->term,
                        $term->type,
                        $term->note,
                        $term->created_at?->toIso8601String(),
                    ]);
                }
            });

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    protected function exportJson(string $type, string $filename)
    {
        $data = [];
        $query = BlockedTerm::query()->orderBy('id');
        if ($type !== 'all') {
            $query->where('type', $type);
        }

        $query->chunk(1000, function ($terms) use (&$data) {
            foreach ($terms as $term) {
                $data[] = [
                    'term' => $term->term,
                    'type' => $term->type,
                    'note' => $term->note,
                    'created_at' => $term->created_at?->toIso8601String(),
                ];
            }
        });

        $payload = [
            'exported_at' => now()->toIso8601String(),
            'type' => $type,
            'count' => count($data),
            'terms' => $data,
        ];

        return response()->json($payload, 200, [
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
