<?php

namespace App\Services;

use App\Models\AdminAuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class AdminAuditLogService
{
    protected ?Request $request;

    public function __construct(?Request $request = null)
    {
        $this->request = $request ?? request();
    }

    public function log(
        User|int $user,
        string $type,
        array|string|null $value = null,
        ?Model $activity = null,
        ?string $actionMsg = null,
        ?string $visibility = '1'
    ): AdminAuditLog {
        $userId = $user instanceof User ? $user->id : $user;

        return AdminAuditLog::create([
            'user_id' => $userId,
            'type' => $type,
            'value' => $value,
            'activity_type' => $activity ? get_class($activity) : null,
            'activity_id' => $activity?->id, // @phpstan-ignore-line
            'action_msg' => $actionMsg,
            'visibility' => $visibility,
        ]);
    }

    public function logInstanceDomainAdded(User|int $user, $instance)
    {
        return $this->log($user, 'instance:added', null, $instance, null, 1);
    }

    public function logInstanceSuspended(User|int $user, $instance)
    {
        return $this->log($user, 'instance:suspended', null, $instance, null, 1);
    }

    public function logInstanceActivated(User|int $user, $instance)
    {
        return $this->log($user, 'instance:activated', null, $instance, null, 1);
    }

    public function logInstanceUpdateSettings(User|int $user, $instance, $changes)
    {
        return $this->log($user, 'instance:updated_settings', $changes, $instance, null, 1);
    }

    public function logInstanceUpdateNotes(User|int $user, $instance, $changes)
    {
        return $this->log($user, 'instance:update_notes', $changes, $instance, null, 1);
    }

    public function logHashtagUpdate(User|int $user, $hashtag, $changes)
    {
        return $this->log($user, 'hashtag:update', $changes, $hashtag, null, 1);
    }

    public function logReportAdminNotesUpdate(User|int $user, $report, $changes)
    {
        return $this->log($user, 'report:update_notes', $changes, $report, null, 1);
    }

    public function logReportDeleteVideo(User|int $user, $report, $changes)
    {
        return $this->log($user, 'report:delete_video', $changes, $report, null, 1);
    }

    public function logReportDeleteComment(User|int $user, $report, $changes)
    {
        return $this->log($user, 'report:delete_comment', $changes, $report, null, 1);
    }

    public function logReportDeleteCommentReply(User|int $user, $report, $changes)
    {
        return $this->log($user, 'report:delete_comment_reply', $changes, $report, null, 1);
    }

    public function logReportDismissAllByAccount(User|int $user, $report, $changes)
    {
        return $this->log($user, 'report:dismiss_all_by_user', $changes, $report, null, 1);
    }

    public function logReportDismiss(User|int $user, $report, $changes)
    {
        return $this->log($user, 'report:dismiss', $changes, $report, null, 1);
    }

    public function logReportUpdateMarkAsNsfw(User|int $user, $report, $changes)
    {
        return $this->log($user, 'report:mark_nsfw', $changes, $report, null, 1);
    }

    public function logProfileAdminNoteUpdate(User|int $user, $report, $changes)
    {
        return $this->log($user, 'profile:update_notes', $changes, $report, null, 1);
    }

    public function logProfileAdminPermissionUpdate(User|int $user, $profile, $changes)
    {
        return $this->log($user, 'profile:permissions', $changes, $profile, null, 1);
    }

    public function logVideoCommentDelete(User|int $user, $video, $changes)
    {
        return $this->log($user, 'video:delete_comment', $changes, $video, null, 1);
    }

    public function logVideoDelete(User|int $user, $video, $changes)
    {
        return $this->log($user, 'video:delete', $changes, $video, null, 1);
    }

    public function logVideoUnpublish(User|int $user, $video, $changes)
    {
        return $this->log($user, 'video:unpublish', $changes, $video, null, 1);
    }

    public function logVideoPublish(User|int $user, $video, $changes)
    {
        return $this->log($user, 'video:publish', $changes, $video, null, 1);
    }
}
