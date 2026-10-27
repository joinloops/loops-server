<?php

namespace App\Federation\Signatures;

final class IncomingSignature
{
    public const RFC9421 = 'rfc9421';

    public const CAVAGE = 'cavage';

    public function __construct(
        public readonly string $scheme,
        public readonly string $keyId,
        public readonly string $base,
        public readonly string $signature,
        public readonly string $algorithm,
        public readonly array $covered,
        public readonly array $alternateBases = [],
    ) {}

    public function actorUrl(): string
    {
        return preg_replace('/#.*$/', '', $this->keyId);
    }

    public function originDomain(): ?string
    {
        $host = parse_url($this->actorUrl(), PHP_URL_HOST);

        return $host ? strtolower($host) : null;
    }

    public function verify(?string $publicKeyPem): bool
    {
        if (! $publicKeyPem) {
            return false;
        }

        $key = openssl_pkey_get_public($publicKeyPem);

        if (! $key) {
            return false;
        }

        if ($this->scheme === self::RFC9421) {
            $details = openssl_pkey_get_details($key);

            if (($details['type'] ?? null) !== OPENSSL_KEYTYPE_RSA) {
                return false;
            }
        }

        foreach ([$this->base, ...$this->alternateBases] as $base) {
            if (openssl_verify($base, $this->signature, $key, OPENSSL_ALGO_SHA256) === 1) {
                return true;
            }
        }

        return false;
    }
}
