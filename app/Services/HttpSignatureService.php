<?php

namespace App\Services;

use App\Federation\Signatures\ByteSequence;
use App\Federation\Signatures\IncomingSignature;
use App\Federation\Signatures\InvalidSignatureException;
use App\Federation\Signatures\StructuredFields;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

class HttpSignatureService
{
    public const RFC9421 = IncomingSignature::RFC9421;

    public const CAVAGE = IncomingSignature::CAVAGE;

    public const RFC9421_LABEL = 'sig1';

    public const RFC9421_ALGORITHM = 'rsa-v1_5-sha256';

    public const MAX_CLOCK_SKEW = 3600;

    public function signRequest(
        string $scheme,
        string $keyId,
        string $privateKey,
        string $method,
        string $url,
        ?string $body = null,
        array $headers = []
    ): array {
        $parsed = parse_url($url);

        if (! $parsed || empty($parsed['host'])) {
            throw new InvalidArgumentException("Invalid URL: {$url}");
        }

        $method = strtoupper($method);
        $authority = $this->authority($parsed);
        $requestTarget = $this->requestTarget($parsed);

        $headers = array_merge([
            'Host' => $authority,
            'Date' => now('UTC')->toRfc7231String(),
        ], $headers);

        if ($scheme === self::RFC9421) {
            return $this->signRfc9421($keyId, $privateKey, $method, $url, $authority, $body, $headers);
        }

        $signedHeaders = ['(request-target)', 'host', 'date'];

        if ($body !== null) {
            $headers['Digest'] = $this->calculateDigest($body);
            $signedHeaders[] = 'digest';
        }

        $signatureString = $this->buildSignatureString($signedHeaders, $headers, $method, $requestTarget);

        $headers['Signature'] = sprintf(
            'keyId="%s",algorithm="rsa-sha256",headers="%s",signature="%s"',
            $keyId,
            implode(' ', $signedHeaders),
            base64_encode($this->rsaSign($signatureString, $privateKey))
        );

        return $headers;
    }

    protected function signRfc9421(
        string $keyId,
        string $privateKey,
        string $method,
        string $url,
        string $authority,
        ?string $body,
        array $headers
    ): array {
        $components = ['@method', '@target-uri', '@authority'];

        if ($body !== null) {
            $headers['Content-Digest'] = $this->contentDigest($body);
            $components[] = 'content-digest';
        }

        $values = [
            '@method' => $method,
            '@target-uri' => $url,
            '@authority' => $authority,
            'content-digest' => $headers['Content-Digest'] ?? null,
        ];

        $params = [
            'created' => now()->timestamp,
            'keyid' => $keyId,
            'alg' => self::RFC9421_ALGORITHM,
        ];

        $signatureParams = StructuredFields::serializeInnerList(
            array_map(fn ($c) => ['value' => $c, 'params' => []], $components),
            $params
        );

        $lines = [];

        foreach ($components as $component) {
            $lines[] = StructuredFields::serializeBareItem($component).': '.$values[$component];
        }

        $lines[] = '"@signature-params": '.$signatureParams;

        $signature = $this->rsaSign(implode("\n", $lines), $privateKey);

        $headers['Signature-Input'] = self::RFC9421_LABEL.'='.$signatureParams;
        $headers['Signature'] = self::RFC9421_LABEL.'='.StructuredFields::serializeBareItem(new ByteSequence($signature));

        return $headers;
    }

    public function parseIncoming(array $headers, string $method, string $requestUri, ?string $body = null, ?string $scheme = null): IncomingSignature
    {
        $headers = $this->normalizeHeaders($headers);

        if (isset($headers['signature-input'])) {
            return $this->parseIncomingRfc9421($headers, $method, $requestUri, $body, $scheme);
        }

        if (isset($headers['signature'])) {
            return $this->parseIncomingCavage($headers, $method, $requestUri, $body);
        }

        throw new InvalidSignatureException('Missing signature');
    }

    public function hasSignature(array $headers): bool
    {
        $headers = $this->normalizeHeaders($headers);

        if (! isset($headers['signature'])) {
            return false;
        }

        if (isset($headers['signature-input'])) {
            return true;
        }

        $value = implode(', ', $headers['signature']);

        return str_contains($value, 'keyId=') && str_contains($value, 'signature=');
    }

    public function peekKeyId(array $headers): ?string
    {
        $headers = $this->normalizeHeaders($headers);

        if (isset($headers['signature-input'])) {
            try {
                foreach (StructuredFields::parseDictionary(implode(', ', $headers['signature-input'])) as $member) {
                    if (is_string($member['params']['keyid'] ?? null)) {
                        return $member['params']['keyid'];
                    }
                }
            } catch (InvalidArgumentException $e) {
                return null;
            }

            return null;
        }

        if (isset($headers['signature'])) {
            return $this->parseSignatureHeader(implode(', ', $headers['signature']))['keyId'] ?? null;
        }

        return null;
    }

    protected function parseIncomingRfc9421(array $headers, string $method, string $requestUri, ?string $body, ?string $scheme): IncomingSignature
    {
        try {
            $inputs = StructuredFields::parseDictionary(implode(', ', $headers['signature-input']));
            $signatures = StructuredFields::parseDictionary(implode(', ', $headers['signature'] ?? []));
        } catch (InvalidArgumentException $e) {
            throw new InvalidSignatureException('Malformed signature: '.$e->getMessage());
        }

        $lastError = 'No usable signature';

        foreach ($inputs as $label => $input) {
            try {
                return $this->buildRfc9421Signature($label, $input, $signatures, $headers, $method, $requestUri, $body, $scheme);
            } catch (InvalidSignatureException $e) {
                $lastError = $e->getMessage();
            }
        }

        throw new InvalidSignatureException($lastError);
    }

    protected function buildRfc9421Signature(
        string $label,
        array $input,
        array $signatures,
        array $headers,
        string $method,
        string $requestUri,
        ?string $body,
        ?string $scheme
    ): IncomingSignature {
        if (empty($input['inner']) || ! is_array($input['value'])) {
            throw new InvalidSignatureException('Signature-Input member is not an inner list');
        }

        $signature = $signatures[$label]['value'] ?? null;

        if (! $signature instanceof ByteSequence) {
            throw new InvalidSignatureException('Missing signature for label '.$label);
        }

        $params = $input['params'];
        $keyId = $params['keyid'] ?? null;

        if (! is_string($keyId) || $keyId === '') {
            throw new InvalidSignatureException('Missing keyid');
        }

        $alg = $params['alg'] ?? null;

        if ($alg !== null && $alg !== self::RFC9421_ALGORITHM) {
            throw new InvalidSignatureException('Unsupported signature algorithm');
        }

        $created = $params['created'] ?? null;
        $now = now()->timestamp;

        if ($created !== null && ! is_int($created)) {
            throw new InvalidSignatureException('Invalid created parameter');
        }

        if ($created !== null && abs($now - $created) > self::MAX_CLOCK_SKEW) {
            throw new InvalidSignatureException('Signature created time is outside the allowed window');
        }

        $expires = $params['expires'] ?? null;

        if ($expires !== null && (! is_int($expires) || $expires < $now)) {
            throw new InvalidSignatureException('Signature has expired');
        }

        $components = [];

        foreach ($input['value'] as $item) {
            if (! is_string($item['value']) || ! empty($item['params'])) {
                throw new InvalidSignatureException('Unsupported covered component');
            }

            $components[] = $item['value'];
        }

        if (count($components) !== count(array_unique($components))) {
            throw new InvalidSignatureException('Duplicate covered component');
        }

        if (! in_array('@method', $components, true)) {
            throw new InvalidSignatureException('Signature must cover @method');
        }

        $coversTarget = in_array('@target-uri', $components, true)
            || ((in_array('@authority', $components, true) || in_array('host', $components, true))
                && (in_array('@path', $components, true) || in_array('@request-target', $components, true)));

        if (! $coversTarget) {
            throw new InvalidSignatureException('Signature must cover the target URI');
        }

        $hasBody = $body !== null && $body !== '';

        if ($hasBody && ! in_array('content-digest', $components, true)) {
            throw new InvalidSignatureException('Signature must cover content-digest');
        }

        if (in_array('content-digest', $components, true)) {
            if (! isset($headers['content-digest']) || ! $this->verifyContentDigest(implode(', ', $headers['content-digest']), $body ?? '')) {
                throw new InvalidSignatureException('Invalid Content-Digest');
            }
        }

        $coversDate = in_array('date', $components, true);

        if ($coversDate && ! $this->isDateValid($headers['date'][0] ?? '')) {
            throw new InvalidSignatureException('Invalid or expired Date header');
        }

        if ($created === null && ! $coversDate) {
            throw new InvalidSignatureException('Signature must include created or cover date');
        }

        $authority = strtolower(trim($headers['host'][0] ?? ''));

        if ($authority === '') {
            throw new InvalidSignatureException('Missing Host header');
        }

        $scheme = strtolower($scheme ?? (parse_url((string) config('app.url'), PHP_URL_SCHEME) ?: 'https'));
        $path = parse_url('http://x'.$requestUri, PHP_URL_PATH) ?: '/';
        $query = parse_url('http://x'.$requestUri, PHP_URL_QUERY);

        $lines = [];

        foreach ($components as $component) {
            $value = match ($component) {
                '@method' => strtoupper($method),
                '@target-uri' => $scheme.'://'.$authority.$requestUri,
                '@authority' => $authority,
                '@scheme' => $scheme,
                '@request-target' => $requestUri,
                '@path' => $path,
                '@query' => '?'.($query ?? ''),
                default => $this->rfc9421FieldValue($component, $headers),
            };

            $lines[] = StructuredFields::serializeBareItem($component).': '.$value;
        }

        $lines[] = '"@signature-params": '.StructuredFields::serializeInnerList($input['value'], $params);

        return new IncomingSignature(
            self::RFC9421,
            $keyId,
            implode("\n", $lines),
            $signature->bytes,
            self::RFC9421_ALGORITHM,
            $components
        );
    }

    protected function rfc9421FieldValue(string $component, array $headers): string
    {
        if (str_starts_with($component, '@')) {
            throw new InvalidSignatureException('Unsupported derived component '.$component);
        }

        if ($component !== strtolower($component) || ! isset($headers[$component])) {
            throw new InvalidSignatureException('Covered header is missing: '.$component);
        }

        return implode(', ', array_map(fn ($v) => trim(preg_replace('/\s*\r?\n\s*/', ' ', $v)), $headers[$component]));
    }

    protected function parseIncomingCavage(array $headers, string $method, string $requestUri, ?string $body): IncomingSignature
    {
        $parsed = $this->parseSignatureHeader(implode(', ', $headers['signature']));

        if (empty($parsed['keyId']) || empty($parsed['signature'])) {
            throw new InvalidSignatureException('Malformed signature header');
        }

        $algorithm = strtolower($parsed['algorithm'] ?? 'rsa-sha256');

        if (! in_array($algorithm, ['rsa-sha256', 'hs2019'], true)) {
            throw new InvalidSignatureException('Unsupported signature algorithm');
        }

        $signedHeaders = isset($parsed['headers'])
            ? preg_split('/\s+/', strtolower(trim($parsed['headers'])))
            : ['date'];

        foreach (['(request-target)', 'host', 'date'] as $required) {
            if (! in_array($required, $signedHeaders, true)) {
                throw new InvalidSignatureException("Missing signed header: {$required}");
            }
        }

        if (! $this->isDateValid($headers['date'][0] ?? '')) {
            throw new InvalidSignatureException('Invalid or expired Date header');
        }

        if (in_array('digest', $signedHeaders, true)) {
            if (! isset($headers['digest']) || ! $this->verifyDigest($headers['digest'][0], $body ?? '')) {
                throw new InvalidSignatureException('Invalid Digest');
            }
        }

        $lines = $this->cavageLines($signedHeaders, $headers, $method, $requestUri);
        $alternates = [];
        $pathOnly = strtok($requestUri, '?');

        if ($pathOnly !== false && $pathOnly !== $requestUri) {
            $alternates[] = implode("\n", $this->cavageLines($signedHeaders, $headers, $method, $pathOnly));
        }

        $signature = base64_decode($parsed['signature'], true);

        if ($signature === false) {
            throw new InvalidSignatureException('Failed to decode signature');
        }

        return new IncomingSignature(
            self::CAVAGE,
            $parsed['keyId'],
            implode("\n", $lines),
            $signature,
            $algorithm,
            $signedHeaders,
            $alternates
        );
    }

    protected function cavageLines(array $signedHeaders, array $headers, string $method, string $requestTarget): array
    {
        $lines = [];

        foreach ($signedHeaders as $name) {
            if ($name === '(request-target)') {
                $lines[] = '(request-target): '.strtolower($method).' '.$requestTarget;

                continue;
            }

            if (! isset($headers[$name])) {
                throw new InvalidSignatureException("Missing required header: {$name}");
            }

            $lines[] = $name.': '.implode(', ', array_map('trim', $headers[$name]));
        }

        return $lines;
    }

    public function contentDigest(string $body): string
    {
        return 'sha-256=:'.base64_encode(hash('sha256', $body, true)).':';
    }

    public function verifyContentDigest(string $header, string $body): bool
    {
        try {
            $digests = StructuredFields::parseDictionary($header);
        } catch (InvalidArgumentException $e) {
            return false;
        }

        $algorithms = ['sha-256' => 'sha256', 'sha-512' => 'sha512'];
        $checked = false;

        foreach ($digests as $name => $member) {
            if (! isset($algorithms[$name])) {
                continue;
            }

            if (! $member['value'] instanceof ByteSequence) {
                return false;
            }

            if (! hash_equals(hash($algorithms[$name], $body, true), $member['value']->bytes)) {
                return false;
            }

            $checked = true;
        }

        return $checked;
    }

    public function normalizeHeaders(array $headers): array
    {
        $normalized = [];

        foreach ($headers as $name => $value) {
            $values = is_array($value) ? array_values($value) : [$value];
            $normalized[strtolower((string) $name)] = array_map('strval', array_filter($values, fn ($v) => $v !== null));
        }

        return array_filter($normalized, fn ($v) => $v !== []);
    }

    public function isDateValid(string $dateHeader): bool
    {
        if ($dateHeader === '') {
            return false;
        }

        try {
            $date = Carbon::parse($dateHeader, 'UTC');
        } catch (\Throwable $e) {
            return false;
        }

        return abs(now('UTC')->timestamp - $date->timestamp) <= self::MAX_CLOCK_SKEW;
    }

    protected function rsaSign(string $data, string $privateKey): string
    {
        $key = openssl_pkey_get_private($privateKey);

        if (! $key) {
            throw new \RuntimeException('Invalid private key');
        }

        $signature = '';

        if (! openssl_sign($data, $signature, $key, OPENSSL_ALGO_SHA256)) {
            throw new \RuntimeException('Failed to sign data');
        }

        return $signature;
    }

    protected function authority(array $parsed): string
    {
        $host = strtolower($parsed['host']);
        $port = $parsed['port'] ?? null;
        $scheme = strtolower($parsed['scheme'] ?? 'https');

        if ($port && ! (($scheme === 'https' && (int) $port === 443) || ($scheme === 'http' && (int) $port === 80))) {
            return $host.':'.$port;
        }

        return $host;
    }

    protected function requestTarget(array $parsed): string
    {
        return ($parsed['path'] ?? '/').(isset($parsed['query']) ? '?'.$parsed['query'] : '');
    }

    /**
     * Verify an HTTP signature
     *
     * @param  string  $signatureHeader  The Signature header value
     * @param  string  $publicKey  The public key PEM
     * @param  array  $headers  The request headers
     * @param  string  $method  The HTTP method
     * @param  string  $path  The request path
     */
    public function verify(
        string $signatureHeader,
        string $publicKey,
        array $headers,
        string $method,
        string $path
    ): bool {
        try {
            $parsed = $this->parseSignatureHeader($signatureHeader);

            if (! isset($parsed['signature']) || ! isset($parsed['headers'])) {
                Log::debug('Invalid signature header format', ['parsed' => $parsed]);

                return false;
            }

            $signatureHeaders = explode(' ', $parsed['headers']);

            $signatureString = $this->buildSignatureString($signatureHeaders, $headers, $method, $path);

            $signature = base64_decode($parsed['signature']);

            if ($signature == false) {
                Log::debug('Failed to decode signature');

                return false;
            }

            $key = openssl_pkey_get_public($publicKey);

            if (! $key) {
                Log::debug('Invalid public key');

                return false;
            }

            $result = openssl_verify($signatureString, $signature, $key, OPENSSL_ALGO_SHA256);

            return $result === 1;
        } catch (\Exception $e) {
            Log::error('Exception during signature verification', [
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Build the signature string
     */
    protected function buildSignatureString(
        array $signatureHeaders,
        array $headers,
        string $method,
        string $path
    ): string {
        $parts = [];

        foreach ($signatureHeaders as $header) {
            $header = strtolower($header);

            if ($header === '(request-target)') {
                $parts[] = '(request-target): '.strtolower($method).' '.$path;
            } else {
                $value = $this->findHeader($headers, $header);

                if ($value === null) {
                    throw new \RuntimeException("Missing required header: {$header}");
                }

                $parts[] = $header.': '.$value;
            }
        }

        return implode("\n", $parts);
    }

    /**
     * Find a header case-insensitively
     */
    protected function findHeader(array $headers, string $name): ?string
    {
        $name = strtolower($name);

        foreach ($headers as $key => $value) {
            if (strtolower($key) === $name) {
                return $value;
            }
        }

        return null;
    }

    /**
     * Parse the Signature header
     */
    public function parseSignatureHeader(string $signature): array
    {
        $parts = [];

        preg_match_all('/(\w+)="([^"\\\\]*(?:\\\\.[^"\\\\]*)*)"/', $signature, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            $parts[$match[1]] = str_replace('\\"', '"', $match[2]);
        }

        return $parts;
    }

    /**
     * Calculate digest for request body
     */
    protected function calculateDigest(string $body): string
    {
        return 'SHA-256='.base64_encode(hash('sha256', $body, true));
    }

    /**
     * Verify digest header
     */
    public function verifyDigest(string $digestHeader, string $body): bool
    {
        $parts = explode('=', $digestHeader, 2);

        if (count($parts) !== 2) {
            return false;
        }

        [$algorithm, $expectedDigest] = $parts;

        switch (strtoupper($algorithm)) {
            case 'SHA-256':
                $actualDigest = base64_encode(hash('sha256', $body, true));
                break;
            case 'SHA-512':
                $actualDigest = base64_encode(hash('sha512', $body, true));
                break;
            default:
                return false;
        }

        return hash_equals($expectedDigest, $actualDigest);
    }
}
