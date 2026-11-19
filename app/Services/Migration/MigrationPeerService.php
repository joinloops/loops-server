<?php

namespace App\Services\Migration;

use App\Federation\Migration;
use App\Models\Instance;
use App\Models\Profile;
use App\Services\ActivityPubService;
use App\Services\SanitizeService;
use App\Services\WebfingerService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class MigrationPeerService
{
    public function resolveHandle(string $handle): ?Profile
    {
        $handle = trim($handle);

        if (str_starts_with($handle, 'https://')) {
            return $this->refreshProfile($handle);
        }

        return app(WebfingerService::class)->lookup($handle);
    }

    public function freshActor(string $uri): ?array
    {
        if (! app(SanitizeService::class)->url($uri, true)) {
            return null;
        }

        $data = app(ActivityPubService::class)->get($uri, [], true, true, true, true);

        if (! is_array($data) || ($data['id'] ?? null) !== $uri) {
            return null;
        }

        return $data;
    }

    public function refreshProfile(string $uri): ?Profile
    {
        $data = $this->freshActor($uri);

        if (! $data) {
            return Profile::where('uri', $uri)->where('local', false)->first();
        }

        return app(Profile::class)->findOrCreateFromUrl($uri, $data, true);
    }

    public function actorAliases(array $actor): array
    {
        return Migration::sanitizeAliases($actor['alsoKnownAs'] ?? []);
    }

    public function actorMovedTo(array $actor): ?string
    {
        $moved = $actor['movedTo'] ?? null;

        if (is_array($moved)) {
            $moved = $moved['id'] ?? null;
        }

        return is_string($moved) ? $moved : null;
    }

    public function isDomainBlocked(string $domain): bool
    {
        return Instance::where('domain', strtolower($domain))->where('is_blocked', true)->exists();
    }

    public function migrationSupport(string $domain, bool $fresh = false): ?array
    {
        $domain = strtolower($domain);
        $key = 'loops:migration:peer:'.$domain;

        if ($fresh) {
            Cache::forget($key);
        }

        $cached = Cache::remember($key, now()->addHour(), function () use ($domain) {
            $nodeinfo = $this->nodeinfo($domain);

            if (! $nodeinfo || strtolower((string) data_get($nodeinfo, 'software.name')) !== 'loops') {
                return ['supported' => false];
            }

            $support = data_get($nodeinfo, 'metadata.loops.migration');

            if (! is_array($support) || (int) ($support['version'] ?? 0) < Migration::VERSION) {
                return ['supported' => false];
            }

            return [
                'supported' => true,
                'version' => (int) $support['version'],
                'inbound' => (bool) ($support['inbound'] ?? false),
                'outbound' => (bool) ($support['outbound'] ?? false),
                'transit' => array_values(array_filter((array) ($support['transit'] ?? []), 'is_string')),
            ];
        });

        return $cached['supported'] ? $cached : null;
    }

    protected function nodeinfo(string $domain): ?array
    {
        $base = 'https://'.$domain;

        if (! app(SanitizeService::class)->url($base.'/', true)) {
            return null;
        }

        try {
            $discovery = Http::timeout(10)
                ->acceptJson()
                ->withHeaders(['User-Agent' => app('user_agent')])
                ->get($base.'/.well-known/nodeinfo');
        } catch (\Throwable $e) {
            return null;
        }

        if (! $discovery->ok()) {
            return null;
        }

        $links = collect((array) data_get($discovery->json(), 'links', []))
            ->filter(fn ($link) => is_array($link) && is_string($link['href'] ?? null) && Migration::host($link['href']) === $domain)
            ->sortByDesc(fn ($link) => (string) ($link['rel'] ?? ''));

        $href = $links->first()['href'] ?? null;

        if (! $href) {
            return null;
        }

        try {
            $response = Http::timeout(10)
                ->acceptJson()
                ->withHeaders(['User-Agent' => app('user_agent')])
                ->get($href);
        } catch (\Throwable $e) {
            return null;
        }

        $json = $response->ok() ? $response->json() : null;

        return is_array($json) ? $json : null;
    }
}
