<?php

namespace App\Services;

use App\Exceptions\UsernameChangeException;
use App\Jobs\Federation\RefreshRemoteProfileJob;
use App\Models\Profile;
use App\Models\User;
use App\Models\UsernameHistory;
use App\Models\UsernameReservation;
use App\Rules\ValidUsername;
use Carbon\CarbonInterface;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class UsernameChangeService
{
    public const MIN_LENGTH = 2;

    public const MAX_LENGTH = 24;

    public function __construct(protected UsernameService $usernames) {}

    public function enabled(): bool
    {
        return (bool) config('loops.usernames.changes.enabled', true);
    }

    public function limit(): int
    {
        return max(0, (int) config('loops.usernames.changes.limit', 3));
    }

    public function windowDays(): int
    {
        return max(1, (int) config('loops.usernames.changes.window_days', 365));
    }

    public function cooldownHours(): int
    {
        return max(0, (int) config('loops.usernames.changes.cooldown_hours', 24));
    }

    public function requiresPassword(User $user): bool
    {
        return blank($user->apple_id);
    }

    public function normalize(?string $username): string
    {
        return ltrim(trim((string) $username), '@');
    }

    public function countedChanges(Profile $profile)
    {
        return UsernameHistory::where('profile_id', $profile->id)
            ->where('source', UsernameHistory::SOURCE_LOCAL_CHANGE)
            ->where('is_revert', false)
            ->where('started_at', '>=', now()->subDays($this->windowDays()))
            ->orderBy('started_at')
            ->get(['id', 'started_at']);
    }

    public function lastLocalChange(Profile $profile): ?UsernameHistory
    {
        return UsernameHistory::where('profile_id', $profile->id)
            ->where('source', UsernameHistory::SOURCE_LOCAL_CHANGE)
            ->orderByDesc('started_at')
            ->orderByDesc('id')
            ->first();
    }

    public function cooldownEndsAt(Profile $profile): ?CarbonInterface
    {
        $last = $this->lastLocalChange($profile);

        if (! $last || ! $last->started_at || $this->cooldownHours() === 0) {
            return null;
        }

        $ends = $last->started_at->copy()->addHours($this->cooldownHours());

        return $ends->isFuture() ? $ends : null;
    }

    public function status(User $user): array
    {
        $profile = $user->profile;
        $reservation = UsernameReservation::where('profile_id', $profile->id)->first();
        $counted = $this->countedChanges($profile);
        $used = $counted->count();
        $remaining = max(0, $this->limit() - $used);
        $cooldownEndsAt = $this->cooldownEndsAt($profile);

        $limitResetsAt = null;
        if ($remaining === 0 && $counted->isNotEmpty()) {
            $limitResetsAt = $counted->first()->started_at->copy()->addDays($this->windowDays());
        }

        $history = UsernameHistory::where('profile_id', $profile->id)
            ->orderByDesc('started_at')
            ->orderByDesc('id')
            ->limit(10)
            ->get()
            ->map(fn (UsernameHistory $row) => [
                'username' => $row->username,
                'source' => $row->source,
                'is_revert' => $row->is_revert,
                'started_at' => $row->started_at?->toIso8601String(),
                'ended_at' => $row->ended_at?->toIso8601String(),
            ])
            ->values();

        return [
            'enabled' => $this->enabled(),
            'username' => $profile->username,
            'domain' => $this->displayDomain(),
            'reserved_username' => $reservation?->username,
            'reserved_at' => $reservation?->reserved_at?->toIso8601String(),
            'limit' => $this->limit(),
            'window_days' => $this->windowDays(),
            'cooldown_hours' => $this->cooldownHours(),
            'changes_used' => $used,
            'changes_remaining' => $remaining,
            'cooldown_ends_at' => $cooldownEndsAt?->toIso8601String(),
            'limit_resets_at' => $limitResetsAt?->toIso8601String(),
            'can_change' => $this->enabled() && $profile->status == 1 && ! $cooldownEndsAt && $remaining > 0,
            'can_revert' => $this->enabled() && $profile->status == 1 && ! $cooldownEndsAt && (bool) $reservation,
            'requires_password' => $this->requiresPassword($user),
            'min_length' => self::MIN_LENGTH,
            'max_length' => self::MAX_LENGTH,
            'history' => $history,
        ];
    }

    public function displayDomain(): ?string
    {
        $host = parse_url((string) config('app.url'), PHP_URL_HOST);
        $port = parse_url((string) config('app.url'), PHP_URL_PORT);

        return $host ? ($port ? $host.':'.$port : $host) : null;
    }

    public function check(User $user, ?string $username): array
    {
        $username = $this->normalize($username);

        try {
            $isRevert = $this->assertChangeable($user, $username);

            return [
                'username' => $username,
                'available' => true,
                'is_revert' => $isRevert,
                'reason' => null,
                'message' => null,
            ];
        } catch (UsernameChangeException $e) {
            return [
                'username' => $username,
                'available' => false,
                'is_revert' => false,
                'reason' => $e->reason,
                'message' => $e->getMessage(),
                ...$e->meta,
            ];
        }
    }

    public function change(User $user, ?string $username, ?string $password = null): Profile
    {
        $username = $this->normalize($username);
        $isRevert = $this->assertChangeable($user, $username);

        if ($this->requiresPassword($user) && (! $password || ! Hash::check($password, $user->password))) {
            throw new UsernameChangeException('password', 'The password you entered is incorrect.');
        }

        $profile = $user->profile;
        $oldUsername = $profile->username;

        try {
            $profile = $this->apply($profile, $username, UsernameHistory::SOURCE_LOCAL_CHANGE, $isRevert, true);
        } catch (QueryException $e) {
            throw new UsernameChangeException('taken', 'This username is not available.');
        }

        app(UserAuditLogService::class)->logUsernameChanged($user, $oldUsername, $username, $isRevert);
        app(UserActivityService::class)->markActive($user);

        AccountService::del($profile->id);

        if (app(ConfigService::class)->federation()) {
            app(FederationDispatcher::class)->dispatchProfileUpdate($profile);
        }

        return $profile;
    }

    public function apply(Profile $profile, string $newUsername, string $source, bool $isRevert = false, bool $reserveOld = false): Profile
    {
        return DB::transaction(function () use ($profile, $newUsername, $source, $isRevert, $reserveOld) {
            $profile = Profile::whereKey($profile->id)->lockForUpdate()->firstOrFail();
            $oldUsername = $profile->username;
            $now = now();

            if ($profile->local && $profile->user_id) {
                $user = User::whereKey($profile->user_id)->lockForUpdate()->first();
                $user?->forceFill(['username' => $newUsername])->save();
            }

            $profile->forceFill(['username' => $newUsername])->save();

            UsernameHistory::where('profile_id', $profile->id)
                ->whereNull('ended_at')
                ->update(['ended_at' => $now, 'updated_at' => $now]);

            UsernameHistory::create([
                'profile_id' => $profile->id,
                'username' => $newUsername,
                'domain' => $profile->local ? null : $profile->domain,
                'local' => (bool) $profile->local,
                'source' => $source,
                'is_revert' => $isRevert,
                'started_at' => $now,
            ]);

            if ($profile->local && $reserveOld && $oldUsername && strtolower($oldUsername) !== strtolower($newUsername)) {
                UsernameReservation::where('profile_id', $profile->id)->delete();
                UsernameReservation::where('username', $oldUsername)->delete();
                UsernameReservation::create([
                    'profile_id' => $profile->id,
                    'username' => $oldUsername,
                    'reserved_at' => $now,
                ]);
            }

            return $profile;
        });
    }

    public function syncRemote(Profile $profile, ?string $preferredUsername): bool
    {
        if ($profile->local || ! $profile->uri || ! is_string($preferredUsername)) {
            return false;
        }

        $preferred = trim($preferredUsername);

        if ($preferred === '' || mb_strlen($preferred) > 100 || ! preg_match('/^[\p{L}\p{N}_.\-]+$/u', $preferred)) {
            return false;
        }

        $host = strtolower((string) parse_url($profile->uri, PHP_URL_HOST));

        if (! $host || ($profile->domain && strtolower($profile->domain) !== $host)) {
            return false;
        }

        $acct = $preferred.'@'.$host;

        if ($acct === $profile->username) {
            return false;
        }

        if (! app(WebfingerService::class)->acctResolvesTo($acct, $profile->uri)) {
            return false;
        }

        $holder = Profile::where('username', $acct)->where('id', '!=', $profile->id)->first();

        if ($holder) {
            if ($holder->local) {
                return false;
            }

            $this->evictStaleRemote($holder);
        }

        $this->apply($profile, $acct, UsernameHistory::SOURCE_REMOTE_UPDATE);

        return true;
    }

    public function claimForNewRemote(string $acct, string $actorUri): bool
    {
        $holder = Profile::where('username', $acct)->first();

        if (! $holder) {
            return true;
        }

        if ($holder->local || $holder->uri === $actorUri) {
            return false;
        }

        if (! app(WebfingerService::class)->acctResolvesTo($acct, $actorUri)) {
            return false;
        }

        $this->evictStaleRemote($holder);

        return true;
    }

    public function evictStaleRemote(Profile $holder): void
    {
        $tombstone = Str::limit('~'.$holder->id.'~'.$holder->username, 250, '');

        DB::transaction(function () use ($holder, $tombstone) {
            $now = now();

            UsernameHistory::where('profile_id', $holder->id)
                ->whereNull('ended_at')
                ->update(['ended_at' => $now, 'updated_at' => $now]);

            Profile::whereKey($holder->id)->update(['username' => $tombstone]);
        });

        AccountService::del($holder->id);

        if ($holder->uri) {
            RefreshRemoteProfileJob::dispatch($holder->uri)->onQueue('actor-update')->afterCommit();
        }
    }

    protected function assertChangeable(User $user, string $username): bool
    {
        $profile = $user->profile;

        if (! $this->enabled()) {
            throw new UsernameChangeException('disabled', 'Username changes are not available on this server.', 403);
        }

        if (! $profile || $profile->status != 1) {
            throw new UsernameChangeException('account', 'Your account cannot change its username right now.', 403);
        }

        if ($username === '') {
            throw new UsernameChangeException('invalid', 'Please enter a username.');
        }

        if ($username === $profile->username) {
            throw new UsernameChangeException('same', 'This is already your username.');
        }

        $validator = Validator::make(
            ['username' => $username],
            ['username' => ['required', 'string', 'min:'.self::MIN_LENGTH, 'max:'.self::MAX_LENGTH, new ValidUsername]]
        );

        if ($validator->fails()) {
            throw new UsernameChangeException('invalid', $validator->errors()->first('username'));
        }

        if ($this->usernames->isReservedWord($username)) {
            throw new UsernameChangeException('reserved', 'This username is reserved.');
        }

        $isCaseChange = strtolower($username) === strtolower($profile->username);

        $reservation = $this->usernames->reservationFor($username);
        $isRevert = $reservation && (string) $reservation->profile_id === (string) $profile->id;

        if (! $isCaseChange) {
            if ($reservation && ! $isRevert) {
                throw new UsernameChangeException('taken', 'This username is not available.');
            }

            $takenByUser = User::where('username', $username)->where('id', '!=', $user->id)->exists();
            $takenByProfile = Profile::where('username', $username)->where('id', '!=', $profile->id)->exists();

            if ($takenByUser || $takenByProfile) {
                throw new UsernameChangeException('taken', 'This username is not available.');
            }
        }

        $cooldownEndsAt = $this->cooldownEndsAt($profile);

        if ($cooldownEndsAt) {
            throw new UsernameChangeException(
                'cooldown',
                'You recently changed your username. You can change it again '.$cooldownEndsAt->diffForHumans().'.',
                422,
                ['available_at' => $cooldownEndsAt->toIso8601String()]
            );
        }

        if (! $isRevert) {
            $counted = $this->countedChanges($profile);

            if ($counted->count() >= $this->limit()) {
                $resetsAt = $counted->isNotEmpty()
                    ? $counted->first()->started_at->copy()->addDays($this->windowDays())
                    : null;

                throw new UsernameChangeException(
                    'limit',
                    'You have reached the limit of '.$this->limit().' username changes per year.',
                    422,
                    ['available_at' => $resetsAt?->toIso8601String()]
                );
            }
        }

        return (bool) $isRevert;
    }
}
