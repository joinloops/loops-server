<?php

namespace App\Services;

use Exception;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class ActivityPubService
{
    const MAX_RESPONSE_SIZE = 1048576;

    public function get($url, $params = [], $signed = true, $validateUrl = true, $validateContentType = true, $bypassCache = false)
    {
        if ($validateUrl) {
            $valid = app(SanitizeService::class)->url($url, true, false);
            if (! $valid) {
                return false;
            }
        }

        $cacheKey = 'activitypub-fetch:'.sha1($url.json_encode($params));

        if (! $bypassCache) {
            $cached = Cache::get($cacheKey);
            if ($cached !== null) {
                return $cached;
            }
        }

        $lock = Cache::lock('activitypub-fetch_lock:'.sha1($url.json_encode($params)), 10);

        try {
            $lock->block(10);

            if (! $bypassCache) {
                $cached = Cache::get($cacheKey);
                if ($cached !== null) {
                    return $cached;
                }
            }

            $result = $this->fetchActivityPub($url, $params, $signed, $validateContentType);

            if ($result !== false && $result !== null) {
                Cache::put($cacheKey, $result, 14400);  // Cache successful results for 4 hours
            } else {
                Cache::put($cacheKey, null, 300);  // Cache failures for only 5 minutes
            }

            return $result;

        } finally {
            $lock->release();
        }
    }

    protected function fetchActivityPub($url, $params, $signed, $validateContentType)
    {
        if (! empty($params)) {
            $url .= (str_contains($url, '?') ? '&' : '?').http_build_query($params);
        }

        $headers = [
            'Content-Type' => 'application/activity+json',
            'Accept' => 'application/activity+json',
            'User-Agent' => app('user_agent'),
        ];

        $dispatch = function (array $headers) use ($url) {
            return Http::withOptions([
                'allow_redirects' => false,
                'stream' => true,
                'on_headers' => function (\Psr\Http\Message\ResponseInterface $response) {
                    $len = $response->getHeaderLine('Content-Length');
                    if ($len !== '' && (int) $len > self::MAX_RESPONSE_SIZE) {
                        throw new \RuntimeException('ActivityPub response exceeds max size');
                    }
                },
            ])
                ->withHeaders($headers)
                ->timeout(5)
                ->connectTimeout(3)
                ->retry(2, 500, fn ($e) => $e instanceof ConnectionException
                    || ($e instanceof RequestException && $e->response->serverError()), throw: false)
                ->get($url);
        };

        try {
            $res = $signed
                ? app(SignedHttpClient::class)->send('GET', app(InstanceActorService::class)->getKeyId(), $url, null, $dispatch, $headers)
                : $dispatch($headers);
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

            if (! in_array($contentType, $acceptedTypes)) {
                return false;
            }
        }

        $body = $res->toPsrResponse()->getBody();
        $contents = '';

        try {
            while (! $body->eof()) {
                $contents .= $body->read(8192);

                if (strlen($contents) > self::MAX_RESPONSE_SIZE) {
                    return false;
                }
            }
        } finally {
            $body->close();
        }

        $decoded = json_decode($contents, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return false;
        }

        return $decoded;
    }
}
