<?php

namespace App\Services\Migration;

use App\Federation\Migration;
use App\Services\ActivityPubService;
use App\Services\InstanceActorService;
use App\Services\SigningService;
use Illuminate\Support\Facades\Cache;

class AttestationService
{
    public const JSON_FLAGS = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;

    public function __construct(
        protected SigningService $signing,
        protected InstanceActorService $instanceActor,
    ) {}

    public function keyId(): string
    {
        return $this->instanceActor->getKeyId();
    }

    public function sign(array $payload): string
    {
        $header = [
            'alg' => 'RS256',
            'kid' => $this->keyId(),
            'typ' => Migration::JWS_TYPE,
        ];

        $input = $this->b64(json_encode($header, self::JSON_FLAGS)).'.'.$this->b64(json_encode($payload, self::JSON_FLAGS));

        $key = openssl_pkey_get_private($this->signing->getPrivateKey());

        if (! $key || ! openssl_sign($input, $signature, $key, OPENSSL_ALGO_SHA256)) {
            throw new MigrationException('Unable to sign migration attestation');
        }

        return $input.'.'.$this->b64($signature);
    }

    public function decode(string $jws): array
    {
        $parts = explode('.', trim($jws));

        if (count($parts) !== 3) {
            throw new MigrationException('Malformed attestation');
        }

        $header = json_decode($this->unb64($parts[0]), true);
        $payload = json_decode($this->unb64($parts[1]), true);
        $signature = $this->unb64($parts[2]);

        if (! is_array($header) || ! is_array($payload) || $signature === '') {
            throw new MigrationException('Malformed attestation');
        }

        return [
            'header' => $header,
            'payload' => $payload,
            'input' => $parts[0].'.'.$parts[1],
            'signature' => $signature,
        ];
    }

    public function verify(string $jws, string $expectedHost): array
    {
        $decoded = $this->decode($jws);
        $header = $decoded['header'];

        if (($header['alg'] ?? null) !== 'RS256' || ($header['typ'] ?? null) !== Migration::JWS_TYPE) {
            throw new MigrationException('Unsupported attestation format');
        }

        $kid = $header['kid'] ?? null;

        if (! is_string($kid) || Migration::host($kid) !== strtolower($expectedHost) || ! str_starts_with($kid, 'https://')) {
            throw new MigrationException('Attestation key does not belong to the expected server');
        }

        $key = $this->publicKeyFor($kid);

        if (! $key || ! $this->checkSignature($decoded, $key)) {
            $key = $this->publicKeyFor($kid, true);

            if (! $key || ! $this->checkSignature($decoded, $key)) {
                throw new MigrationException('Attestation signature is invalid');
            }
        }

        $decoded['key_fingerprint'] = $this->fingerprint($key);

        return $decoded;
    }

    public function verifyOwn(string $jws): array
    {
        $decoded = $this->decode($jws);

        if (($decoded['header']['kid'] ?? null) !== $this->keyId() || ! $this->checkSignature($decoded, $this->signing->getPublicKey())) {
            throw new MigrationException('Attestation was not issued by this server');
        }

        return $decoded;
    }

    public function publicKeyFor(string $kid, bool $fresh = false): ?string
    {
        $cacheKey = 'loops:migration:key:'.sha1($kid);

        if (! $fresh && ($cached = Cache::get($cacheKey))) {
            return $cached;
        }

        if ($kid === $this->keyId()) {
            return $this->signing->getPublicKey();
        }

        $owner = strtok($kid, '#');
        $document = app(ActivityPubService::class)->get($owner, [], true, true, true, $fresh);

        if (! is_array($document)) {
            return null;
        }

        $publicKey = $document['publicKey'] ?? null;

        if (is_array($publicKey) && array_is_list($publicKey)) {
            $publicKey = collect($publicKey)->firstWhere('id', $kid);
        }

        if (! is_array($publicKey) || ($publicKey['id'] ?? null) !== $kid || ! is_string($publicKey['publicKeyPem'] ?? null)) {
            return null;
        }

        if (openssl_pkey_get_public($publicKey['publicKeyPem']) === false) {
            return null;
        }

        Cache::put($cacheKey, $publicKey['publicKeyPem'], now()->addHour());

        return $publicKey['publicKeyPem'];
    }

    public function fingerprint(string $pem): string
    {
        $der = base64_decode(preg_replace('/-----[A-Z ]+-----|\s+/', '', $pem), true);

        return 'sha256:'.hash('sha256', $der === false ? $pem : $der);
    }

    public function hash(string $data): string
    {
        return hash('sha256', $data);
    }

    public function canonical($value): string
    {
        return json_encode($this->sortKeys($value), self::JSON_FLAGS);
    }

    public function leafHash(int $position, string $kind, string $id, array $data): string
    {
        return hash('sha256', "\x00".$this->canonical([
            'data' => $data,
            'id' => $id,
            'kind' => $kind,
            'position' => $position,
        ]));
    }

    public function mapLeafHash(string $source, string $target): string
    {
        return hash('sha256', "\x00".$this->canonical(['source' => $source, 'target' => $target]));
    }

    public function merkleRoot(array $leaves): string
    {
        $leaves = array_values($leaves);

        if ($leaves === []) {
            return hash('sha256', '');
        }

        return bin2hex($this->treeHash(array_map(fn ($leaf) => hex2bin($leaf), $leaves)));
    }

    public function prefixed(string $hash): string
    {
        return str_starts_with($hash, 'sha256:') ? $hash : 'sha256:'.$hash;
    }

    public function unprefixed(?string $hash): ?string
    {
        if (! is_string($hash)) {
            return null;
        }

        return str_starts_with($hash, 'sha256:') ? substr($hash, 7) : $hash;
    }

    protected function treeHash(array $nodes): string
    {
        $count = count($nodes);

        if ($count === 1) {
            return $nodes[0];
        }

        $split = 1;

        while ($split * 2 < $count) {
            $split *= 2;
        }

        $left = $this->treeHash(array_slice($nodes, 0, $split));
        $right = $this->treeHash(array_slice($nodes, $split));

        return hash('sha256', "\x01".$left.$right, true);
    }

    protected function checkSignature(array $decoded, string $pem): bool
    {
        $key = openssl_pkey_get_public($pem);

        if (! $key) {
            return false;
        }

        return openssl_verify($decoded['input'], $decoded['signature'], $key, OPENSSL_ALGO_SHA256) === 1;
    }

    protected function sortKeys($value)
    {
        if (! is_array($value)) {
            return $value;
        }

        if (array_is_list($value)) {
            return array_map(fn ($item) => $this->sortKeys($item), $value);
        }

        ksort($value, SORT_STRING);

        return array_map(fn ($item) => $this->sortKeys($item), $value);
    }

    protected function b64(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    protected function unb64(string $data): string
    {
        $decoded = base64_decode(strtr($data, '-_', '+/'), true);

        return $decoded === false ? '' : $decoded;
    }
}
