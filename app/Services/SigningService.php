<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

class SigningService
{
    const PUBLIC_KEY = 'pki_public.txt';

    const PRIVATE_KEY = 'pki_private.txt';

    const CACHE_PUBLIC_KEY = 'pki::public';

    const CACHE_PRIVATE_KEY = 'pki::private';

    public function getPublicKey()
    {
        $this->checkActive();

        return Cache::get(self::CACHE_PUBLIC_KEY);
    }

    public function getPrivateKey()
    {
        $this->checkActive();

        return Cache::get(self::CACHE_PRIVATE_KEY);
    }

    public function checkActive()
    {
        if (! Cache::has(self::CACHE_PUBLIC_KEY) ||
            ! Cache::has(self::CACHE_PRIVATE_KEY)) {
            $this->fetchFromStorageOrCreate();
        }

        return 1;
    }

    public function fetchFromStorageOrCreate()
    {
        if (Storage::exists(self::PUBLIC_KEY) && Storage::exists(self::PRIVATE_KEY)) {
            $public = Storage::get(self::PUBLIC_KEY);
            $private = Storage::get(self::PRIVATE_KEY);

            // Validate both keys are loaded
            if ($public === null) {
                throw new \RuntimeException('Failed to read public key files from storage');
            }
            if ($private === null) {
                throw new \RuntimeException('Failed to read private key files from storage');
            }

            Cache::rememberForever(self::CACHE_PUBLIC_KEY, fn () => $public);
            Cache::rememberForever(self::CACHE_PRIVATE_KEY, fn () => $private);

            return 1;
        }

        return $this->createSigningKeys();
    }

    public function createSigningKeys()
    {
        if (Storage::exists(self::PUBLIC_KEY)) {
            throw new \Exception('Keys already exists');
        }

        $keys = $this->generateKeypair();

        Storage::put(self::PUBLIC_KEY, $keys['public']);
        Storage::put(self::PRIVATE_KEY, $keys['private']);

        Cache::rememberForever(self::CACHE_PUBLIC_KEY, fn () => $keys['public']);
        Cache::rememberForever(self::CACHE_PRIVATE_KEY, fn () => $keys['private']);

        return 1;
    }

    /**
     * Generate a new RSA keypair
     *
     * @return array ['public' => string, 'private' => string]
     */
    public function generateKeypair(): array
    {
        $config = [
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ];

        $resource = openssl_pkey_new($config);

        if (! $resource) {
            throw new \RuntimeException('Failed to generate RSA keypair');
        }

        $privateKey = '';
        if (! openssl_pkey_export($resource, $privateKey)) {
            throw new \RuntimeException('Failed to export private key');
        }

        $details = openssl_pkey_get_details($resource);
        if (! $details || ! isset($details['key'])) {
            throw new \RuntimeException('Failed to get public key');
        }

        return [
            'public' => $details['key'],
            'private' => $privateKey,
        ];
    }
}
