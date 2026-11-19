<?php

namespace App\Services\Migration;

use App\Federation\Migration;
use App\Services\SanitizeService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TransitClient
{
    public function discover(string $transit, bool $fresh = false): ?array
    {
        $transit = rtrim($transit, '/');

        if (! app(SanitizeService::class)->url($transit, true)) {
            return null;
        }

        $key = 'loops:migration:transit:'.sha1($transit);

        if ($fresh) {
            Cache::forget($key);
        }

        return Cache::remember($key, now()->addHour(), function () use ($transit) {
            try {
                $response = Http::timeout(10)
                    ->acceptJson()
                    ->withHeaders(['User-Agent' => app('user_agent')])
                    ->get($transit.'/.well-known/loops-transit');
            } catch (\Throwable $e) {
                return;
            }

            if (! $response->ok()) {
                return;
            }

            $json = $response->json();

            if (! is_array($json) || (int) ($json['version'] ?? 0) < 1 || empty($json['keys']) || ! is_array($json['keys'])) {
                return;
            }

            return $json;
        });
    }

    public function submitIntent(string $transit, string $intentJws): ?array
    {
        $document = $this->discover($transit);

        if (! $document) {
            return null;
        }

        $endpoint = $this->endpoint($transit, $document, 'intents', '/api/v1/intents');
        $response = $this->post($endpoint, ['intent' => $intentJws]);

        $entry = $response['entry'] ?? null;

        return is_array($entry) ? $entry : null;
    }

    public function submitReceipt(string $transit, string $intentJws, string $receiptJws): ?array
    {
        $document = $this->discover($transit);

        if (! $document) {
            return null;
        }

        $endpoint = $this->endpoint($transit, $document, 'receipts', '/api/v1/receipts');
        $response = $this->post($endpoint, ['intent' => $intentJws, 'receipt' => $receiptJws]);

        $seal = $response['seal'] ?? null;

        if (! is_array($seal) || ! is_string($seal['jws'] ?? null)) {
            return null;
        }

        if (! $this->verifySeal($transit, $seal, $intentJws, $receiptJws)) {
            Log::warning('Transit returned a seal that failed verification', ['transit' => $transit]);

            return null;
        }

        return $seal;
    }

    public function verifySeal(string $transit, array $seal, string $intentJws, string $receiptJws): bool
    {
        if (! function_exists('sodium_crypto_sign_verify_detached')) {
            return false;
        }

        $jws = $seal['jws'] ?? null;

        if (! is_string($jws)) {
            return false;
        }

        $parts = explode('.', $jws);

        if (count($parts) !== 3) {
            return false;
        }

        $header = json_decode($this->unb64($parts[0]), true);
        $payload = json_decode($this->unb64($parts[1]), true);
        $signature = $this->unb64($parts[2]);

        if (! is_array($header) || ! is_array($payload) || ($header['alg'] ?? null) !== 'EdDSA' || strlen($signature) !== SODIUM_CRYPTO_SIGN_BYTES) {
            return false;
        }

        foreach ([false, true] as $fresh) {
            $document = $this->discover($transit, $fresh);
            $key = collect($document['keys'] ?? [])->first(fn ($k) => is_array($k) && ($k['kid'] ?? null) === ($header['kid'] ?? null));

            if (! $key || ($key['kty'] ?? null) !== 'OKP' || ($key['crv'] ?? null) !== 'Ed25519') {
                continue;
            }

            $publicKey = $this->unb64((string) ($key['x'] ?? ''));

            if (strlen($publicKey) !== SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES) {
                continue;
            }

            if (sodium_crypto_sign_verify_detached($signature, $parts[0].'.'.$parts[1], $publicKey)) {
                return ($payload['type'] ?? null) === Migration::SEAL_TYPE
                    && hash_equals(hash('sha256', $intentJws), (string) app(AttestationService::class)->unprefixed($payload['intentHash'] ?? ''))
                    && hash_equals(hash('sha256', $receiptJws), (string) app(AttestationService::class)->unprefixed($payload['receiptHash'] ?? ''));
            }
        }

        return false;
    }

    protected function endpoint(string $transit, array $document, string $name, string $fallback): string
    {
        $endpoint = data_get($document, 'endpoints.'.$name);

        if (is_string($endpoint) && Migration::host($endpoint) === Migration::host($transit)) {
            return $endpoint;
        }

        return rtrim($transit, '/').$fallback;
    }

    protected function post(string $url, array $body): ?array
    {
        try {
            $response = Http::timeout(20)
                ->acceptJson()
                ->withHeaders(['User-Agent' => app('user_agent')])
                ->post($url, $body);
        } catch (\Throwable $e) {
            Log::warning('Transit request failed', ['url' => $url, 'error' => $e->getMessage()]);

            return null;
        }

        if (! $response->successful()) {
            Log::warning('Transit request rejected', ['url' => $url, 'status' => $response->status()]);

            return null;
        }

        $json = $response->json();

        return is_array($json) ? $json : null;
    }

    protected function unb64(string $data): string
    {
        $decoded = base64_decode(strtr($data, '-_', '+/'), true);

        return $decoded === false ? '' : $decoded;
    }
}
