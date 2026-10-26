<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

class AccountSwitcherService
{
    public const SESSION_LINKED_ACCOUNTS = 'auth:linked_accounts';

    public const SESSION_REMEMBER = 'auth:remember_chosen';

    public const MAX_LINKED_ACCOUNTS = 5;

    public function linkCurrentUser(bool $remember = false): void
    {
        if (! Auth::check()) {
            return;
        }

        $userId = (int) Auth::id();
        $linked = $this->getLinkedIds();

        $linked = array_values(array_filter($linked, fn ($id) => (int) $id !== $userId));
        array_unshift($linked, $userId);

        $linked = array_slice($linked, 0, self::MAX_LINKED_ACCOUNTS);

        Session::put(self::SESSION_LINKED_ACCOUNTS, $linked);
        Session::put(self::SESSION_REMEMBER, $remember);
    }

    public function getLinkedIds(): array
    {
        $ids = Session::get(self::SESSION_LINKED_ACCOUNTS, []);
        if (! is_array($ids)) {
            return [];
        }

        return array_values(array_unique(array_map('intval', $ids)));
    }

    public function getLinkedAccounts(): array
    {
        $ids = $this->getLinkedIds();
        if (empty($ids)) {
            return [];
        }

        $query = User::whereIn('id', $ids);

        $users = $query->get()->keyBy('id');

        $activeId = (string) Auth::id();
        $accounts = [];
        $stale = [];

        foreach ($ids as $id) {
            if (! isset($users[$id])) {
                $stale[] = $id;

                continue;
            }

            $u = $users[$id];

            if (! $this->isSwitchable($u)) {
                $stale[] = $id;

                continue;
            }

            $accountData = AccountService::getByUserId($u->id, true, true);

            if (empty($accountData)) {
                $stale[] = $id;

                continue;
            }

            $accountData['id'] = (string) $u->id;
            $accountData['is_active'] = (string) $u->id === $activeId;

            $accounts[] = $accountData;
        }

        if (! empty($stale)) {
            $clean = array_values(array_diff($ids, $stale));
            Session::put(self::SESSION_LINKED_ACCOUNTS, $clean);
        }

        return $accounts;
    }

    /**
     * Switch the active session to a different linked account.
     */
    public function switchTo(int $userId): array
    {
        $userId = (int) $userId;
        $linked = $this->getLinkedIds();

        if (! in_array($userId, $linked, true)) {
            return ['success' => false, 'reason' => 'not_linked'];
        }

        $user = User::find($userId);

        if (! $user) {
            $this->removeFromList($userId);

            return ['success' => false, 'reason' => 'not_found'];
        }

        if ((int) $user->status === 6) {
            $this->removeFromList($userId);

            return ['success' => false, 'reason' => 'disabled'];
        }

        if (! $this->isSwitchable($user)) {
            $this->removeFromList($userId);

            return ['success' => false, 'reason' => 'not_found'];
        }

        $linked = array_values(array_filter($linked, fn ($id) => (int) $id !== $userId));
        array_unshift($linked, $userId);

        $remember = (bool) Session::get(self::SESSION_REMEMBER, false);

        Auth::login($user, $remember);

        Session::put('password_hash_'.Auth::getDefaultDriver(), $user->getAuthPassword());

        Session::put(self::SESSION_LINKED_ACCOUNTS, $linked);
        Session::put(self::SESSION_REMEMBER, $remember);

        return ['success' => true, 'reason' => null];
    }

    /**
     * Remove an account from the linked list.
     */
    public function removeFromList(int $userId): array
    {
        $userId = (int) $userId;

        $linked = array_values(array_filter(
            $this->getLinkedIds(),
            fn ($id) => (int) $id !== $userId
        ));

        Session::put(self::SESSION_LINKED_ACCOUNTS, $linked);

        if ((int) Auth::id() !== $userId) {
            return [
                'linked' => $this->getLinkedIds(),
                'logged_in' => Auth::check(),
                'active_id' => Auth::id() ? (int) Auth::id() : null,
            ];
        }

        Auth::logout();

        $remember = (bool) Session::get(self::SESSION_REMEMBER, false);

        foreach ($linked as $candidateId) {
            $next = User::find($candidateId);

            if ($next && $this->isSwitchable($next)) {
                Auth::login($next, $remember);
                Session::put('password_hash_'.Auth::getDefaultDriver(), $next->getAuthPassword());
                Session::put(self::SESSION_LINKED_ACCOUNTS, $linked);
                Session::put(self::SESSION_REMEMBER, $remember);

                return [
                    'linked' => $this->getLinkedIds(),
                    'logged_in' => true,
                    'active_id' => (int) Auth::id(),
                ];
            }

            $linked = array_values(array_filter($linked, fn ($id) => (int) $id !== (int) $candidateId));
            Session::put(self::SESSION_LINKED_ACCOUNTS, $linked);
        }

        Session::invalidate();
        Session::regenerateToken();

        return [
            'linked' => [],
            'logged_in' => false,
            'active_id' => null,
        ];
    }

    /**
     * Full multi-account logout. Clears the entire linked list, logs the
     * guard out, and invalidates the session.
     */
    public function logoutAll(): void
    {
        Auth::logout();
        Session::forget(self::SESSION_LINKED_ACCOUNTS);
        Session::forget(self::SESSION_REMEMBER);
        Session::invalidate();
        Session::regenerateToken();
    }

    protected function isSwitchable(User $user): bool
    {
        if (! in_array($user->status, [1, 7, 8], true)) {
            return false;
        }

        return ! app(AccountDeletionService::class)->isLocked($user);
    }

    /**
     * Has the user already linked the maximum number of accounts?
     */
    public function isAtCapacity(): bool
    {
        return count($this->getLinkedIds()) >= self::MAX_LINKED_ACCOUNTS;
    }
}
