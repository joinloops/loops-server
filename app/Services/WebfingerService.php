<?php

namespace App\Services;

use App\Models\InstanceActor;
use App\Models\Profile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WebfingerService
{
    /**
     * Look up a local resource
     *
     * @param  string  $resource  The resource identifier (e.g., acct:user@domain.com)
     */
    public function lookupLocal(string $resource): ?array
    {
        if (str_starts_with($resource, 'https')) {
            $profileMatch = app(SanitizeService::class)->matchUrlTemplate(
                url: $resource,
                templates: [
                    '/ap/users/{profileId}',
                ],
                useAppHost: true,
                constraints: ['profileId' => '\d+', 'username' => '[a-zA-Z0-9_]+']
            );

            if ($profileMatch && isset($profileMatch['profileId'])) {
                $actor = Profile::where('id', $profileMatch['profileId'])->whereLocal(true)->first();
                $resource = "acct:{$actor->username}@{$this->getLocalDomain()}";
            } else {
                $actor = null;
            }

            if ($actor) {
                return $this->buildWebfingerResponse($resource, $actor);
            }
        }

        $parsed = $this->parseResource($resource);

        if (! $parsed) {
            return null;
        }

        if ($parsed['domain'] !== $this->getLocalDomain()) {
            return null;
        }

        if ($parsed['username'] === $this->getLocalDomain()) {
            return $this->buildInstanceActorWebfingerResponse();
        }

        $actor = Profile::where('username', $parsed['username'])
            ->where('local', true)
            ->first();

        if (! $actor) {
            return null;
        }

        return $this->buildWebfingerResponse($resource, $actor);
    }

    /**
     * Look up a remote resource
     *
     * @param  string  $resource  The resource identifier
     */
    public function lookupRemote(string $resource): ?array
    {
        $parsed = $this->parseResource($resource);

        if (! $parsed) {
            return null;
        }

        $webfingerUrl = "https://{$parsed['domain']}/.well-known/webfinger?resource=".urlencode($resource);

        try {
            $response = Http::timeout(10)
                ->withHeaders([
                    'Accept' => 'application/jrd+json, application/json',
                    'User-Agent' => app('user_agent'),
                ])
                ->get($webfingerUrl);

            if ($response->successful()) {
                return $response->json();
            }

            Log::warning('Failed to fetch remote webfinger', [
                'resource' => $resource,
                'status' => $response->status(),
                'domain' => $parsed['domain'],
            ]);
        } catch (\Exception $e) {
            Log::error('Exception during webfinger lookup', [
                'resource' => $resource,
                'error' => $e->getMessage(),
            ]);
        }

        return null;
    }

    /**
     * Find or create a remote actor from webfinger
     */
    public function findOrCreateRemoteActor(string $resource): ?Profile
    {
        if (str_starts_with($resource, '@')) {
            $resource = substr($resource, 1);
        }

        $webfinger = $this->lookupRemote($resource);

        if (! $webfinger) {
            return null;
        }

        $actorUrl = $this->extractActorUrl($webfinger);

        if (! $actorUrl) {
            return null;
        }

        if (app(SanitizeService::class)->url($actorUrl, true) == false) {
            return null;
        }

        return Profile::findOrCreateFromUrl($actorUrl, null, true);
    }

    /**
     * Find or create a remote actor from webfinger
     */
    public function findOrCreateRemoteInstanceActor(string $resource): ?InstanceActor
    {
        if (str_starts_with($resource, '@')) {
            $resource = substr($resource, 1);
        }

        $webfinger = $this->lookupRemote($resource);

        if (! $webfinger) {
            return null;
        }

        $actorUrl = $this->extractActorUrl($webfinger);

        if (! $actorUrl) {
            return null;
        }

        if (app(SanitizeService::class)->url($actorUrl, true) == false) {
            return null;
        }

        return InstanceActor::findOrCreateFromUrl($actorUrl);
    }

    /**
     * Parse a resource identifier
     *
     * @return array|null Returns ['username' => string, 'domain' => string] or null
     */
    protected function parseResource(string $resource): ?array
    {
        // Handle acct: scheme
        if (preg_match('/^acct:(.+)@(.+)$/', $resource, $matches)) {
            return [
                'username' => $matches[1],
                'domain' => $matches[2],
            ];
        }

        // Handle bare username@domain
        if (preg_match('/^(.+)@(.+)$/', $resource, $matches)) {
            return [
                'username' => $matches[1],
                'domain' => $matches[2],
            ];
        }

        // Handle URLs
        if (filter_var($resource, FILTER_VALIDATE_URL)) {
            $parsed = parse_url($resource);
            if ($parsed && isset($parsed['host'])) {
                // Extract username from path if possible
                $path = trim($parsed['path'] ?? '', '/');
                $parts = explode('/', $path);

                return [
                    'username' => end($parts),
                    'domain' => $parsed['host'],
                ];
            }
        }

        return null;
    }

    /**
     * Get the local domain
     */
    protected function getLocalDomain(): string
    {
        $url = config('app.url');

        return parse_url($url, PHP_URL_HOST);
    }

    protected function getAvatarLink(Profile $actor): ?array
    {
        $avatarUrl = $actor->avatar;

        if (! $avatarUrl) {
            return null;
        }

        return [
            'rel' => 'http://webfinger.net/rel/avatar',
            'type' => 'image/jpeg',
            'href' => $avatarUrl,
        ];
    }

    /**
     * Build a webfinger response
     */
    protected function buildWebfingerResponse(string $resource, Profile $actor): array
    {
        $actorUrl = $actor->getActorId();
        $actorPublicUrl = $actor->getPublicUrl();

        return [
            'subject' => $resource,
            'aliases' => [
                $actorPublicUrl,
                $actorUrl,
            ],
            'links' => array_filter([
                [
                    'rel' => 'http://webfinger.net/rel/profile-page',
                    'type' => 'text/html',
                    'href' => $actor->getPublicUrl(),
                ],
                [
                    'rel' => 'self',
                    'type' => 'application/activity+json',
                    'href' => $actorUrl,
                ],
                $this->getAvatarLink($actor),
            ]),
        ];
    }

    protected function buildInstanceActorWebfingerResponse(): array
    {
        $domain = $this->getLocalDomain();
        $publicUrl = 'https://'.$domain.'/about?instance_actor=true';
        $actorUrl = 'https://'.$domain.'/ap/actor';
        $resource = 'acct:'.$domain.'@'.$domain;

        return [
            'subject' => $resource,
            'aliases' => [
                $actorUrl,
            ],
            'links' => [
                [
                    'rel' => 'http://webfinger.net/rel/profile-page',
                    'type' => 'text/html',
                    'href' => $publicUrl,
                ],
                [
                    'rel' => 'self',
                    'type' => 'application/activity+json',
                    'href' => $actorUrl,
                ],
            ],
        ];
    }

    /**
     * Extract the ActivityPub actor URL from webfinger response
     */
    protected function extractActorUrl(array $webfinger): ?string
    {
        if (! isset($webfinger['links'])) {
            return null;
        }

        foreach ($webfinger['links'] as $link) {
            if (($link['rel'] ?? '') === 'self' &&
                ($link['type'] ?? '') === 'application/activity+json') {
                return $link['href'] ?? null;
            }
        }

        return null;
    }

    /**
     * Resolve a mention to an actor
     *
     * @param  string  $mention  Format: @username@domain or full acct:
     */
    public function resolveMention(string $mention): ?Profile
    {
        $mention = ltrim($mention, '@');

        if (! str_contains($mention, '@')) {
            return Profile::where('username', $mention)
                ->where('local', true)
                ->first();
        }

        return $this->findOrCreateRemoteActor("acct:{$mention}");
    }
}
