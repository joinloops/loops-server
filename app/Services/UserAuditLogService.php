<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserAuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class UserAuditLogService
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
        ?Model $activity = null
    ): UserAuditLog {
        $userId = $user instanceof User ? $user->id : $user;

        return UserAuditLog::create([
            'user_id' => $userId,
            'type' => $type,
            'value' => $value,
            'activity_type' => $activity ? get_class($activity) : null,
            'activity_id' => $activity?->getKey(),
            'ip_address' => $this->getIpAddress(),
            'client_id' => $this->getClientId(),
        ]);
    }

    public function logForCurrentUser(
        string $type,
        array|string|null $value = null,
        ?Model $activity = null
    ): ?UserAuditLog {
        $user = Auth::user();

        if (! $user) {
            return null;
        }

        return $this->log($user, $type, $value, $activity);
    }

    public function logPasswordChanged(User|int $user): UserAuditLog
    {
        return $this->log($user, 'password_changed');
    }

    public function logAtomFeedEnabled(User|int $user): UserAuditLog
    {
        return $this->log($user, 'atom_enabled');
    }

    public function logAtomFeedDisabled(User|int $user): UserAuditLog
    {
        return $this->log($user, 'atom_disabled');
    }

    public function logEmailChanged(
        User|int $user,
        string $oldEmail,
        string $newEmail
    ): UserAuditLog {
        return $this->log($user, 'email_changed', [
            'old_email' => $oldEmail,
            'new_email' => $newEmail,
        ]);
    }

    public function logTwoFactorEnabled(User|int $user): UserAuditLog
    {
        return $this->log($user, 'two_factor_enabled');
    }

    public function logTwoFactorDisabled(User|int $user): UserAuditLog
    {
        return $this->log($user, 'two_factor_disabled');
    }

    public function logLogin(User|int $user, array $additionalData = []): UserAuditLog
    {
        return $this->log($user, 'login', [
            'user_agent' => $this->getUserAgent(),
            ...$additionalData,
        ]);
    }

    public function logProfileUpdated(
        User|int $user,
        array $changedFields = [],
        array $additionalData = []
    ): UserAuditLog {
        return $this->log($user, 'profile_updated', [
            'changed_fields' => $changedFields,
            ...$additionalData,
        ]);
    }

    public function logProfileAvatarUpdated(
        User|int $user,
    ): UserAuditLog {
        return $this->log($user, 'avatar_updated');
    }

    public function logProfileAvatarDeleted(
        User|int $user,
    ): UserAuditLog {
        return $this->log($user, 'avatar_deleted');
    }

    public function logAccountDataSettingsUpdated(
        User|int $user,
        array $changedFields = [],
    ): UserAuditLog {
        return $this->log($user, 'account_data_settings_updated', [
            'changed_fields' => $changedFields,
        ]);
    }

    public function logAccountEnablePushNotifications(
        User|int $user,
        string $platform,
    ): UserAuditLog {
        return $this->log($user, 'push_notifications_enabled', [
            'platform' => $platform,
        ]);
    }

    public function logAccountDisabledPushNotifications(
        User|int $user,
        string $platform,
    ): UserAuditLog {
        return $this->log($user, 'push_notifications_disabled', [
            'platform' => $platform,
        ]);
    }

    public function logAccountContentSettingsUpdate(
        User|int $user,
        array $changedFields = [],
    ): UserAuditLog {
        return $this->log($user, 'content_settings', [
            'old' => $changedFields['old'],
            'new' => $changedFields['new'],
        ]);
    }

    public function logAccountStarterKitSettings(
        User|int $user,
        array $changedFields = [],
    ): UserAuditLog {
        return $this->log($user, 'starter_kit_state', [
            'old' => $changedFields['old'],
            'new' => $changedFields['new'],
        ]);
    }

    public function logAccountDmSettings(
        User|int $user,
        array $changedFields = [],
    ): UserAuditLog {
        return $this->log($user, 'dm_state', [
            'old' => $changedFields['old'],
            'new' => $changedFields['new'],
        ]);
    }

    public function logAccountAddProfileLink(
        User|int $user,
        array $changedFields = [],
    ): UserAuditLog {
        return $this->log($user, 'profile_links_add', [
            'old' => $changedFields['old'],
            'new' => $changedFields['new'],
        ]);
    }

    public function logAccountDeleteProfileLink(
        User|int $user,
        array $changedFields = [],
    ): UserAuditLog {
        return $this->log($user, 'profile_links_delete', [
            'old' => $changedFields['old'],
            'new' => $changedFields['new'],
        ]);
    }

    public function logAccountDataSelectiveExport(
        User|int $user,
        $activity = null
    ): UserAuditLog {
        return $this->log($user, 'selective_export', null, $activity);
    }

    public function logPasswordReset(User|int $user, array $additionalData = []): UserAuditLog
    {
        return $this->log($user, 'password_reset', [
            ...$additionalData,
        ]);
    }

    public function logEmailVerified(User|int $user, array $additionalData = []): UserAuditLog
    {
        return $this->log($user, 'email_verified', [
            ...$additionalData,
        ]);
    }

    public function getRecentLogs(User|int $user, int $days = 30, int $limit = 50)
    {
        $userId = $user instanceof User ? $user->id : $user;

        return UserAuditLog::forUser($userId)
            ->recent($days)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    public function getSecurityLogs(User|int $user, int $days = 90, int $limit = 100)
    {
        $userId = $user instanceof User ? $user->id : $user;

        return UserAuditLog::forUser($userId)
            ->whereIn('type', [
                'password_changed',
                'email_changed',
                'two_factor_enabled',
                'two_factor_disabled',
                'login',
                'password_reset',
            ])
            ->recent($days)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    public function getSuspiciousActivity(User|int $user, int $hours = 24)
    {
        $userId = $user instanceof User ? $user->id : $user;

        return UserAuditLog::forUser($userId)
            ->ofType('login')
            ->where('created_at', '>=', now()->subHours($hours))
            ->select('ip_address')
            ->groupBy('ip_address')
            ->havingRaw('COUNT(*) > 3')
            ->get();
    }

    protected function getIpAddress(): ?string
    {
        if (! $this->request) {
            return null;
        }

        return $this->request->ip();
    }

    protected function getClientId(): ?string
    {
        // todo
        return null;
    }

    protected function getUserAgent(): ?string
    {
        if (! $this->request) {
            return null;
        }

        return $this->request->header('User-Agent');
    }

    public function bulkLog(array $activities): void
    {
        $logs = collect($activities)->map(function ($activity) {
            return [
                'user_id' => $activity['user_id'],
                'type' => $activity['type'],
                'value' => isset($activity['value']) ? json_encode($activity['value']) : null,
                'activity_type' => $activity['activity_type'] ?? null,
                'activity_id' => $activity['activity_id'] ?? null,
                'ip_address' => $this->getIpAddress(),
                'client_id' => $this->getClientId(),
                'created_at' => now(),
                'updated_at' => now(),
            ];
        })->toArray();

        UserAuditLog::insert($logs);
    }
}
