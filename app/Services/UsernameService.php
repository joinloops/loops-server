<?php

namespace App\Services;

use App\Models\UsernameReservation;

class UsernameService
{
    public function reserved()
    {
        return [
            'admin', 'administrator', 'root', 'system', 'sysadmin',
            'support', 'help', 'helpdesk', 'service',

            'loops', 'loop', 'official', 'staff', 'team', 'teamLoops',
            'pixelfed',

            'moderator', 'mod', 'mods', 'moderation',

            'new', 'discover', 'explore', 'trending', 'popular',
            'featured', 'verified', 'recommended',

            'user', 'username', 'account', 'profile',
            'everyone', 'all', 'users',

            'inbox', 'outbox', 'followers', 'following',
            'actor', 'shared', 'public', 'instance',

            'api', 'oauth', 'token', 'auth', 'login',
            'register', 'signup', 'signout', 'logout',
            'webhook', 'callback', 'feed', 'rss',

            'info', 'contact', 'news', 'press', 'legal',
            'privacy', 'terms', 'tos', 'about', 'abuse',
            'security', 'dmca', 'copyright',

            'verified', 'official', 'real', 'authentic',
            'message', 'messages',
        ];
    }

    public function wildcardPatterns()
    {
        return [
            'admin*',
        ];
    }

    public function isReserved(string $username, ?int $exceptProfileId = null): bool
    {
        if ($this->isReservedWord($username)) {
            return true;
        }

        return $this->isHeldReservation($username, $exceptProfileId);
    }

    public function isHeldReservation(string $username, ?int $exceptProfileId = null): bool
    {
        return UsernameReservation::where('username', $username)
            ->when($exceptProfileId, fn ($q) => $q->where('profile_id', '!=', $exceptProfileId))
            ->exists();
    }

    public function reservationFor(string $username): ?UsernameReservation
    {
        return UsernameReservation::where('username', $username)->first();
    }

    public function isReservedWord(string $username): bool
    {
        $username = strtolower($username);

        if (in_array($username, $this->reserved())) {
            return true;
        }

        foreach ($this->wildcardPatterns() as $pattern) {
            if ($this->matchesPattern($username, $pattern)) {
                return true;
            }
        }

        return false;
    }

    protected function matchesPattern(string $username, string $pattern): bool
    {
        $regex = '/^'.str_replace('\*', '.*', preg_quote($pattern, '/')).'$/i';

        return preg_match($regex, $username) === 1;
    }
}
