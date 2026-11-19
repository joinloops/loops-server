<?php

namespace App\Services\Migration;

use App\Federation\Migration;
use App\Models\Profile;
use App\Services\AccountService;
use App\Services\ActivityPubCacheService;
use App\Services\FederationDispatcher;

class AccountAliasService
{
    public function __construct(protected MigrationPeerService $peers) {}

    public function list(Profile $profile): array
    {
        $aliases = $profile->aliases();

        if ($aliases === []) {
            return [];
        }

        $profiles = Profile::whereIn('uri', $aliases)->get()->keyBy('uri');

        return array_map(function ($uri) use ($profiles) {
            $known = $profiles->get($uri);

            return [
                'uri' => $uri,
                'domain' => Migration::host($uri),
                'username' => $known?->username,
                'name' => $known?->name,
                'avatar' => $known?->avatar,
            ];
        }, $aliases);
    }

    public function add(Profile $profile, string $handle): array
    {
        if ((int) $profile->status !== 1) {
            throw new MigrationException('This account cannot add aliases right now.', 'ineligible', 403);
        }

        $aliases = $profile->aliases();

        if (count($aliases) >= Migration::MAX_ALIASES) {
            throw new MigrationException('You can add up to '.Migration::MAX_ALIASES.' aliases.', 'limit', 422);
        }

        $remote = $this->peers->resolveHandle($handle);

        if (! $remote) {
            throw new MigrationException('We could not find that account. Check the handle and try again.', 'not_found', 404);
        }

        if ($remote->local) {
            throw new MigrationException('Aliases must be accounts on other servers.', 'same_server', 422);
        }

        if (in_array($remote->uri, $aliases, true)) {
            throw new MigrationException('That account is already an alias.', 'duplicate', 422);
        }

        $aliases[] = $remote->uri;

        $this->save($profile, $aliases);

        return $this->list($profile->fresh());
    }

    public function remove(Profile $profile, string $uri): array
    {
        $aliases = array_values(array_filter($profile->aliases(), fn ($alias) => $alias !== $uri));

        $this->save($profile, $aliases);

        return $this->list($profile->fresh());
    }

    protected function save(Profile $profile, array $aliases): void
    {
        $profile->forceFill(['also_known_as' => $aliases ?: null])->save();

        AccountService::del($profile->id);

        $cache = app(ActivityPubCacheService::class);
        $cache->forget($cache->profileKey((int) $profile->id));

        app(FederationDispatcher::class)->dispatchProfileUpdate($profile->fresh());
    }
}
