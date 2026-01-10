<?php

namespace App\Services;

use Exception;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class ActivityPubService
{
    public function get($url, $params = [], $signed = true, $validateUrl = true, $validateContentType = true)
    {
        if ($validateUrl) {
            $valid = app(SanitizeService::class)->url($url, true);
            if (! $valid) {
                return false;
            }
        }

        $cacheKey = 'activitypub-fetch:'.sha1($url.json_encode($params));

        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        $lock = Cache::lock('activitypub-fetch_lock:'.sha1($url.json_encode($params)), 10);

        try {
            $lock->block(10);

            $cached = Cache::get($cacheKey);
            if ($cached !== null) {
                return $cached;
            }

            $result = $this->fetchActivityPub($url, $params, $signed, $validateContentType);

            if ($result !== false) {
                Cache::put($cacheKey, $result, 14400);
            } else {
                Cache::put($cacheKey, null, 14400);
            }

            return $result;

        } finally {
            $lock->release();
        }
    }

    protected function fetchActivityPub($url, $params, $signed, $validateContentType)
    {
        $parsedUrl = parse_url($url);

        $headers = [
            'Host' => $parsedUrl['host'],
            'Date' => now()->toRfc7231String(),
            'Content-Type' => 'application/activity+json',
            'Accept' => 'application/activity+json',
            'User-Agent' => app('user_agent'),
        ];

        if ($signed) {
            $signature = app(HttpSignatureService::class)->instanceSign($headers, 'GET', $parsedUrl['path'] ?? '/');
            $headers['Signature'] = $signature;
        }

        try {
            $res = Http::withOptions([
                'allow_redirects' => [
                    'max' => 2,
                    'protocols' => ['https'],
                ]])
                ->withHeaders($headers)
                ->timeout(5)
                ->connectTimeout(3)
                ->retry(2, 500)
                ->get($url, $params);
        } catch (RequestException $e) {
            return false;
        } catch (ConnectionException $e) {
            return false;
        } catch (Exception $e) {
            return false;
        }

        if (! $res->ok()) {
            return false;
        }

        if (! $res->header('Content-Type')) {
            return false;
        }

        $acceptedTypes = [
            'application/activity+json; charset=utf-8',
            'application/activity+json',
            'application/ld+json; profile="https://www.w3.org/ns/activitystreams"',
        ];

        /** @var string|null $contentType */
        $contentType = $res->header('Content-Type');

        if ($validateContentType) {
            if ($contentType === null) {
                return false;
            }

            // Check if NOT in accepted types
            if (! in_array($contentType, $acceptedTypes)) {
                return false;
            }
        }

        return $res->json();
    }
}
