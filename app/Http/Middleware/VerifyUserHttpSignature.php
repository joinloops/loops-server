<?php

namespace App\Http\Middleware;

use App\Jobs\Federation\DiscoverInstance;
use App\Models\Profile;
use App\Services\ActivityPubService;
use App\Services\HttpSignatureService;
use App\Services\SanitizeService;
use Carbon\Carbon;
use Closure;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class VerifyUserHttpSignature
{
    protected $signatureService;

    public function __construct(HttpSignatureService $signatureService)
    {
        $this->signatureService = $signatureService;
    }

    public function handle(Request $request, Closure $next)
    {
        $signature = $request->header('Signature');

        if (! $signature) {
            return $this->unauthorized('Missing signature header');
        }

        try {
            $parsed = $this->parseSignatureHeader($signature);
        } catch (Exception $e) {
            throw $e;
        }

        $keyId = $parsed['keyId'] ?? null;

        if (! $keyId) {
            return $this->unauthorized('Missing keyId in signature');
        }

        $actorUrl = preg_replace('/#.*$/', '', $keyId);

        $originDomain = parse_url($actorUrl, PHP_URL_HOST);
        if ($originDomain) {
            $request->attributes->set('activitypub_origin_domain', $originDomain);
        }

        $signedHeadersList = isset($parsed['headers'])
            ? explode(' ', strtolower($parsed['headers']))
            : [];

        $requiredHeaders = ['(request-target)', 'host', 'date'];
        foreach ($requiredHeaders as $required) {
            if (! in_array($required, $signedHeadersList)) {
                return $this->unauthorized('Missing required signed header: '.$required);
            }
        }

        $headers = $this->buildSignedHeaders($request, $signedHeadersList);

        if (! $this->isDateValid($headers['Date'] ?? '')) {
            return $this->unauthorized('Invalid or expired Date header');
        }

        if (! app(SanitizeService::class)->url($actorUrl, true)) {
            return $this->unauthorized('Invalid actor');
        }

        if (in_array('digest', $signedHeadersList)) {
            $digest = $request->header('Digest');
            if (! $digest) {
                return $this->unauthorized('Digest header was signed but not present');
            }

            if (! $this->signatureService->verifyDigest($digest, $request->getContent())) {
                return $this->unauthorized('Invalid Digest header');
            }
        }

        $profile = Profile::where('uri', $actorUrl)->first();
        if ($profile && $profile->public_key) {
            if ($this->verifyWithKey($signature, $profile->public_key, $headers, $request)) {
                $request->attributes->set('activitypub_actor', $profile);

                return $next($request);
            }
            if (config('logging.dev_log')) {
                Log::debug('ActivityPub signature verification failed with cached Profile key, fetching fresh', [
                    'actor' => $actorUrl,
                ]);
            }
        }

        $actorData = $this->fetchActorData($actorUrl);

        if (! $actorData || ! isset($actorData['publicKey']['publicKeyPem'])) {
            $isDeleteActivity = $this->isDeleteActivity($request);

            if ($isDeleteActivity) {
                if (config('logging.dev_log')) {
                    Log::info('Accepting Delete activity despite missing actor data', [
                        'actor' => $actorUrl,
                        'reason' => 'Actor likely deleted',
                    ]);
                }

                return response()->json(['message' => 'Accepted'], 201);
            }

            return $this->unauthorized('Unable to fetch public key');
        }

        if (($actorData['type'] ?? null) !== 'Person') {
            if (config('logging.dev_log')) {
                Log::warning('Rejected non-Person actor for user inbox', [
                    'actor' => $actorUrl,
                    'type' => $actorData['type'] ?? 'unknown',
                ]);
            }

            return $this->unauthorized('User inboxes only accept Person actors');
        }

        $publicKey = $actorData['publicKey']['publicKeyPem'];

        if (! $this->verifyWithKey($signature, $publicKey, $headers, $request)) {
            return $this->unauthorized('Invalid signature');
        }

        $actor = $this->updateOrCreateActorProfile($actorUrl, $actorData);

        if (! $actor) {
            return response()->json(['message' => 'Accepted'], 201);
        }

        $request->attributes->set('activitypub_actor', $actor);

        return $next($request);
    }

    /**
     * Build the headers array based on what was actually signed
     */
    protected function buildSignedHeaders(Request $request, array $signedHeadersList): array
    {
        $headers = [];

        foreach ($signedHeadersList as $headerName) {
            switch ($headerName) {
                case '(request-target)':
                    $headers['(request-target)'] = strtolower($request->method()).' '.$request->getRequestUri();
                    break;

                case 'host':
                    $headers['Host'] = $request->header('Host');
                    break;

                case 'date':
                    $headers['Date'] = $request->header('Date');
                    break;

                case 'content-type':
                    if ($request->hasHeader('Content-Type')) {
                        $headers['Content-Type'] = $request->header('Content-Type');
                    }
                    break;

                case 'digest':
                    if ($request->hasHeader('Digest')) {
                        $headers['Digest'] = $request->header('Digest');
                    }
                    break;

                case 'content-length':
                    if ($request->hasHeader('Content-Length')) {
                        $headers['Content-Length'] = $request->header('Content-Length');
                    }
                    break;

                case 'user-agent':
                    if ($request->hasHeader('User-Agent')) {
                        $headers['User-Agent'] = $request->header('User-Agent');
                    }
                    break;

                case 'accept':
                    if ($request->hasHeader('Accept')) {
                        $headers['Accept'] = $request->header('Accept');
                    }
                    break;

                default:
                    $headerValue = $request->header($headerName);
                    if ($headerValue) {
                        $properCase = implode('-', array_map('ucfirst', explode('-', $headerName)));
                        $headers[$properCase] = $headerValue;
                    }
                    break;
            }
        }

        return $headers;
    }

    protected function verifyWithKey(string $signature, string $publicKey, array $headers, Request $request): bool
    {
        return $this->signatureService->verify(
            $signature,
            $publicKey,
            $headers,
            $request->method(),
            $request->getRequestUri()
        );
    }

    protected function parseSignatureHeader(string $signature): array
    {
        $parts = [];

        if (! preg_match_all('/(\w+)="([^"\\\\]*(?:\\\\.[^"\\\\]*)*)"/', $signature, $matches, PREG_SET_ORDER)) {
            throw new Exception('Malformed signature header');
        }

        foreach ($matches as $match) {
            $parts[$match[1]] = str_replace('\\"', '"', $match[2]);
        }

        if (! isset($parts['keyId']) || ! isset($parts['signature'])) {
            throw new Exception('Missing required signature components');
        }

        return $parts;
    }

    protected function isDateValid(string $dateHeader): bool
    {
        if ($dateHeader === '') {
            return false;
        }

        try {
            $date = Carbon::parse($dateHeader, 'UTC');
        } catch (\Throwable $e) {
            return false;
        }

        $now = now('UTC');
    
        // Reject if more than 1 hour in the future
        if ($date->isAfter($now->copy()->addHour())) {
            return false;
        }
    
        // Reject if more than 1 hour in the past
        if ($date->isBefore($now->copy()->subHour())) {
            return false;
        }

        Return true;
    }

    protected function fetchActorData(string $actorUrl)
    {
        $lockKey = 'api:f:fetch_actor:'.sha1(strtolower($actorUrl));

        return cache()->lock($lockKey, 30)->get(function () use ($actorUrl) {
            try {
                $response = app(ActivityPubService::class)->get($actorUrl);

                if ($response) {
                    return $response;
                } else {
                    if (config('logging.dev_log')) {
                        Log::warning('Failed to fetch ActivityPub actor', [
                            'url' => $actorUrl,
                        ]);
                    }

                    return false;
                }

            } catch (Exception $e) {
                if (config('logging.dev_log')) {
                    Log::error('Exception fetching ActivityPub actor', [
                        'url' => $actorUrl,
                        'error' => $e->getMessage(),
                    ]);
                }

                return false;
            }

        });
    }

    protected function updateOrCreateActor(string $actorUrl, array $actorData): ?Profile
    {
        return $this->updateOrCreateActorProfile($actorUrl, $actorData);
    }

    protected function updateOrCreateActorProfile(string $actorUrl, array $actorData)
    {
        $domain = parse_url($actorUrl, PHP_URL_HOST);
        $appDomain = parse_url(config('app.url'), PHP_URL_HOST);
        if (strtolower($domain) === strtolower($appDomain)) {
            return false;
        }
        $username = app(SanitizeService::class)->cleanPlainText($actorData['preferredUsername']);
        $acct = $username.'@'.$domain;

        $res = Profile::updateOrCreate(
            [
                'uri' => $actorUrl,
            ],
            [
                'username' => $acct,
                'name' => app(SanitizeService::class)->cleanPlainText($actorData['name'] ?? $username),
                'bio' => app(SanitizeService::class)->cleanHtmlWithSpacing($actorData['summary'] ?? null),
                'inbox_url' => $actorData['inbox'] ?? null,
                'avatar' => data_get($actorData, 'icon.url'),
                'outbox_url' => $actorData['outbox'] ?? null,
                'followers_url' => $actorData['followers'] ?? null,
                'following_url' => $actorData['following'] ?? null,
                'shared_inbox_url' => data_get($actorData, 'endpoints.sharedInbox', null) ?? null,
                'public_key' => data_get($actorData, 'publicKey.publicKeyPem', null) ?? null,
                'manuallyApprovesFollowers' => data_get($actorData, 'manuallyApprovesFollowers', false),
                'last_fetched_at' => now(),
                'local' => false,
                'domain' => $domain,
            ]
        );

        DiscoverInstance::dispatch($actorUrl)->onQueue('activitypub-in');

        return $res;

    }

    protected function unauthorized(string $message): \Illuminate\Http\JsonResponse
    {
        return response()->json(['error' => $message], 401);
    }

    /**
     * Check if the request contains a Delete activity
     */
    protected function isDeleteActivity(Request $request): bool
    {
        try {
            $body = json_decode($request->getContent(), true);

            if (! $body || ! isset($body['type'])) {
                return false;
            }

            return $body['type'] === 'Delete';
        } catch (\Throwable $e) {
            return false;
        }
    }
}
