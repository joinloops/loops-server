<?php

namespace App\Services;

use Closure;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SignedHttpClient
{
    public const RFC9421 = HttpSignatureService::RFC9421;

    public const CAVAGE = HttpSignatureService::CAVAGE;

    public const NO_RETRY_STATUSES = [404, 408, 410, 429];

    protected const PREFERENCE_KEY = 'loops:httpsig:scheme:';

    public function __construct(
        protected HttpSignatureService $signatures,
        protected SigningService $signing,
    ) {}

    public function post(string $keyId, string $url, array|string $body, ?int $timeout = null, ?string $privateKey = null): Response
    {
        $payload = is_string($body) ? $body : json_encode($body, JSON_UNESCAPED_SLASHES);
        $timeout ??= (int) config('loops.federation.delivery.timeout', 10);

        return $this->send('POST', $keyId, $url, $payload, function (array $headers) use ($url, $payload, $timeout) {
            return Http::timeout($timeout)
                ->withHeaders($headers)
                ->withBody($payload, 'application/activity+json')
                ->post($url);
        }, [
            'Content-Type' => 'application/activity+json',
            'Accept' => 'application/activity+json',
        ], $privateKey);
    }

    public function send(
        string $method,
        string $keyId,
        string $url,
        ?string $body,
        Closure $dispatch,
        array $headers = [],
        ?string $privateKey = null
    ): Response {
        $host = $this->host($url);
        $privateKey ??= $this->signing->getPrivateKey();
        $headers = array_merge(['User-Agent' => app('user_agent')], $headers);

        $first = $this->preferredScheme($host);
        $response = $dispatch($this->signatures->signRequest($first, $keyId, $privateKey, $method, $url, $body, $headers));

        if ($response->successful()) {
            $this->remember($host, $first);

            return $response;
        }

        if (! $this->doubleKnockEnabled() || ! $this->shouldKnockAgain($response, $first)) {
            return $response;
        }

        $second = $first === self::RFC9421 ? self::CAVAGE : self::RFC9421;
        $retry = $dispatch($this->signatures->signRequest($second, $keyId, $privateKey, $method, $url, $body, $headers));

        if ($retry->successful()) {
            $this->remember($host, $second);

            if (config('logging.dev_log')) {
                Log::debug('HTTP signature double-knock succeeded with fallback scheme', [
                    'host' => $host,
                    'first' => $first,
                    'first_status' => $response->status(),
                    'scheme' => $second,
                ]);
            }
        }

        return $retry;
    }

    protected function shouldKnockAgain(Response $response, string $scheme): bool
    {
        $status = $response->status();

        if ($status >= 500) {
            return $scheme === self::RFC9421;
        }

        return $status >= 400 && ! in_array($status, self::NO_RETRY_STATUSES, true);
    }

    public function preferredScheme(?string $host): string
    {
        $default = $this->defaultScheme();

        if (! $this->doubleKnockEnabled() || ! $host) {
            return $default;
        }

        $cached = Cache::get(self::PREFERENCE_KEY.$host);

        return in_array($cached, [self::RFC9421, self::CAVAGE], true) ? $cached : $default;
    }

    public function noteInboundScheme(?string $host, string $scheme): void
    {
        if (! $host || $scheme !== self::RFC9421 || ! $this->doubleKnockEnabled()) {
            return;
        }

        $key = self::PREFERENCE_KEY.strtolower($host);

        if (Cache::get($key) === null) {
            Cache::put($key, self::RFC9421, now()->addDays($this->ttlDays(self::RFC9421)));
        }
    }

    public function forget(string $host): void
    {
        Cache::forget(self::PREFERENCE_KEY.strtolower($host));
    }

    protected function remember(?string $host, string $scheme): void
    {
        if (! $host || ! $this->doubleKnockEnabled()) {
            return;
        }

        $key = self::PREFERENCE_KEY.$host;

        if (Cache::get($key) === $scheme) {
            return;
        }

        Cache::put($key, $scheme, now()->addDays($this->ttlDays($scheme)));
    }

    protected function defaultScheme(): string
    {
        $configured = config('loops.federation.signatures.default', self::RFC9421);

        return $configured === self::CAVAGE ? self::CAVAGE : self::RFC9421;
    }

    protected function doubleKnockEnabled(): bool
    {
        return (bool) config('loops.federation.signatures.double_knock', true);
    }

    protected function ttlDays(string $scheme): int
    {
        return $scheme === self::RFC9421
            ? max(1, (int) config('loops.federation.signatures.rfc9421_ttl_days', 30))
            : max(1, (int) config('loops.federation.signatures.cavage_ttl_days', 7));
    }

    protected function host(string $url): ?string
    {
        $parsed = parse_url($url);

        if (empty($parsed['host'])) {
            return null;
        }

        return strtolower($parsed['host']).(isset($parsed['port']) ? ':'.$parsed['port'] : '');
    }
}
