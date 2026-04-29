<?php

namespace App\Jobs\Federation;

use App\Models\Instance;
use App\Models\InstanceActor;
use App\Models\Profile;
use App\Services\ActivityPubService;
use App\Services\HttpSignatureService;
use App\Services\SanitizeService;
use App\Services\WebfingerService;
use Carbon\Carbon;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessInboxActivityWithVerification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 300;

    public $tries = 3;

    public $backoff = [60, 300, 900];

    protected array $activity;

    protected array $headers;

    protected string $method;

    protected string $requestUri;

    protected string $rawBody;

    protected ?Profile $targetActor;

    protected bool $isUserInbox;

    public function __construct(
        array $activity,
        array $headers,
        string $method,
        string $requestUri,
        string $rawBody,
        ?Profile $targetActor = null,
        bool $isUserInbox = false
    ) {
        $this->activity = $activity;
        $this->headers = $headers;
        $this->method = $method;
        $this->requestUri = $requestUri;
        $this->rawBody = $rawBody;
        $this->targetActor = $targetActor;
        $this->isUserInbox = $isUserInbox;
    }

    public function handle(
        HttpSignatureService $signatureService,
        ActivityPubService $activityPubService,
        SanitizeService $sanitizeService,
        WebfingerService $webfingerService
    ) {
        try {
            $verificationResult = $this->verifySignature(
                $signatureService,
                $activityPubService,
                $sanitizeService,
                $webfingerService
            );

            if (! $verificationResult['valid']) {
                Log::warning('HTTP signature verification failed in queue', [
                    'activity_type' => $this->activity['type'] ?? 'unknown',
                    'activity_id' => $this->activity['id'] ?? null,
                    'reason' => $verificationResult['reason'] ?? 'unknown',
                ]);

                return;
            }

            $verifiedActor = $verificationResult['actor'];

            if (($this->activity['type'] ?? null) === 'Delete') {
                if (! $this->validateDeleteOrigin($verifiedActor)) {
                    Log::info('Delete dropped at origin validation', [
                        'activity_id' => $this->activity['id'] ?? null,
                        'actor' => is_object($verifiedActor) ? ($verifiedActor->uri ?? null) : null,
                    ]);

                    return;
                }
            }

            if (! ($verifiedActor instanceof Profile)) {
                $verifiedActor = isset($verifiedActor->uri)
                    ? Profile::where('uri', $verifiedActor->uri)->first()
                    : null;
            }

            if ($this->isUserInbox && $this->targetActor) {
                ProcessInboxActivity::dispatch($this->activity, $verifiedActor, $this->targetActor)
                    ->onQueue('activitypub-in');
            } else {
                ProcessSharedInboxActivity::dispatch($this->activity, $verifiedActor)
                    ->onQueue('activitypub-in');
            }

        } catch (Exception $e) {
            Log::error('Failed to verify and process inbox activity', [
                'activity_type' => $this->activity['type'] ?? 'unknown',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    protected function verifySignature(
        HttpSignatureService $signatureService,
        ActivityPubService $activityPubService,
        SanitizeService $sanitizeService,
        WebfingerService $webfingerService
    ): array {
        $signature = $this->headers['signature'][0] ?? null;

        if (! $signature) {
            return ['valid' => false, 'reason' => 'Missing signature'];
        }

        try {
            $parsed = $this->parseSignatureHeader($signature);
        } catch (Exception $e) {
            return ['valid' => false, 'reason' => 'Malformed signature: '.$e->getMessage()];
        }

        $keyId = $parsed['keyId'] ?? null;
        if (! $keyId) {
            return ['valid' => false, 'reason' => 'Missing keyId'];
        }

        $actorUrl = preg_replace('/#.*$/', '', $keyId);
        $originDomain = parse_url($actorUrl, PHP_URL_HOST);

        $signedHeadersList = isset($parsed['headers'])
            ? explode(' ', strtolower($parsed['headers']))
            : [];

        $requiredHeaders = ['(request-target)', 'host', 'date'];
        foreach ($requiredHeaders as $required) {
            if (! in_array($required, $signedHeadersList)) {
                return ['valid' => false, 'reason' => "Missing signed header: {$required}"];
            }
        }

        $headers = $this->buildSignedHeaders($signedHeadersList);

        if (! $this->isDateValid($headers['Date'] ?? '')) {
            return ['valid' => false, 'reason' => 'Invalid or expired Date header'];
        }

        if (! $sanitizeService->url($actorUrl, true)) {
            return ['valid' => false, 'reason' => 'Invalid actor URL'];
        }

        if (in_array('digest', $signedHeadersList)) {
            $digest = $this->headers['digest'][0] ?? null;
            if (! $digest || ! $signatureService->verifyDigest($digest, $this->rawBody)) {
                return ['valid' => false, 'reason' => 'Invalid Digest'];
            }
        }

        $profile = Profile::where('uri', $actorUrl)->first();
        if ($profile && $profile->public_key) {
            if ($this->verifyWithKey($signature, $profile->public_key, $headers, $signatureService)) {
                return ['valid' => true, 'actor' => $profile];
            }
        }

        $instanceActor = InstanceActor::where('uri', $actorUrl)->first();
        if ($instanceActor && $instanceActor->public_key) {
            if ($this->verifyWithKey($signature, $instanceActor->public_key, $headers, $signatureService)) {
                return ['valid' => true, 'actor' => $instanceActor];
            }
        }

        if ($originDomain && ! $instanceActor) {
            try {
                $webfingerActor = $webfingerService->findOrCreateRemoteInstanceActor("acct:{$originDomain}@{$originDomain}");
                if ($webfingerActor && $webfingerActor->public_key) {
                    if ($this->verifyWithKey($signature, $webfingerActor->public_key, $headers, $signatureService)) {
                        return ['valid' => true, 'actor' => $webfingerActor];
                    }
                }
            } catch (Exception $e) {
                // Continue to fetch
            }
        }

        $actorData = $this->fetchActorData($actorUrl, $activityPubService);

        if (! $actorData || ! isset($actorData['publicKey']['publicKeyPem'])) {
            if (($this->activity['type'] ?? null) === 'Delete') {
                Log::info('Accepting Delete despite missing actor (likely deleted)', [
                    'actor' => $actorUrl,
                ]);

                $existing = Profile::where('uri', $actorUrl)->first();
                if ($existing) {
                    return ['valid' => true, 'actor' => $existing];
                }

                return ['valid' => true, 'actor' => (object) ['uri' => $actorUrl, 'domain' => $originDomain]];
            }

            return ['valid' => false, 'reason' => 'Unable to fetch public key'];
        }

        $publicKey = $actorData['publicKey']['publicKeyPem'];

        if (! $this->verifyWithKey($signature, $publicKey, $headers, $signatureService)) {
            return ['valid' => false, 'reason' => 'Signature verification failed with fresh key'];
        }

        $actor = $this->updateOrCreateActor($actorUrl, $actorData);

        return ['valid' => true, 'actor' => $actor ?? (object) ['uri' => $actorUrl, 'domain' => $originDomain]];
    }

    protected function buildSignedHeaders(array $signedHeadersList): array
    {
        $headers = [];

        foreach ($signedHeadersList as $headerName) {
            switch ($headerName) {
                case '(request-target)':
                    $headers['(request-target)'] = strtolower($this->method).' '.$this->requestUri;
                    break;

                case 'host':
                    $headers['Host'] = $this->headers['host'][0] ?? '';
                    break;

                case 'date':
                    $headers['Date'] = $this->headers['date'][0] ?? '';
                    break;

                case 'digest':
                    if (isset($this->headers['digest'])) {
                        $headers['Digest'] = $this->headers['digest'][0];
                    }
                    break;

                case 'content-type':
                    if (isset($this->headers['content-type'])) {
                        $headers['Content-Type'] = $this->headers['content-type'][0];
                    }
                    break;

                case 'content-length':
                    if (isset($this->headers['content-length'])) {
                        $headers['Content-Length'] = $this->headers['content-length'][0];
                    }
                    break;

                default:
                    if (isset($this->headers[$headerName])) {
                        $properCase = implode('-', array_map('ucfirst', explode('-', $headerName)));
                        $headers[$properCase] = $this->headers[$headerName][0];
                    }
                    break;
            }
        }

        return $headers;
    }

    protected function verifyWithKey(string $signature, string $publicKey, array $headers, HttpSignatureService $signatureService): bool
    {
        return $signatureService->verify(
            $signature,
            $publicKey,
            $headers,
            $this->method,
            $this->requestUri
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

        if ($date->isAfter($now->copy()->addHour())) {
            return false;
        }

        if ($date->isBefore($now->copy()->subHour())) {
            return false;
        }

        return true;
    }

    protected function fetchActorData(string $actorUrl, ActivityPubService $activityPubService)
    {
        $lockKey = 'api:f:fetch_actor:'.sha1(strtolower($actorUrl));

        return cache()->lock($lockKey, 30)->get(function () use ($actorUrl, $activityPubService) {
            try {
                return $activityPubService->get($actorUrl);
            } catch (Exception $e) {
                Log::error('Failed to fetch actor in queue', [
                    'url' => $actorUrl,
                    'error' => $e->getMessage(),
                ]);

                return false;
            }
        });
    }

    protected function updateOrCreateActor(string $actorUrl, array $actorData): Profile|InstanceActor|null
    {
        return match ($actorData['type']) {
            'Person' => $this->updateOrCreateActorProfile($actorUrl, $actorData),
            'Application' => $this->updateOrCreateActorApplication($actorUrl, $actorData),
            default => null,
        };
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
            ['uri' => $actorUrl],
            [
                'username' => $acct,
                'name' => app(SanitizeService::class)->cleanPlainText($actorData['name'] ?? $username),
                'bio' => app(SanitizeService::class)->cleanHtmlWithSpacing($actorData['summary'] ?? null),
                'inbox_url' => $actorData['inbox'] ?? null,
                'avatar' => data_get($actorData, 'icon.url'),
                'remote_url' => data_get($actorData, 'url'),
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

    protected function updateOrCreateActorApplication(string $actorUrl, array $actorData): ?InstanceActor
    {
        $domain = parse_url($actorUrl, PHP_URL_HOST);
        $appDomain = parse_url(config('app.url'), PHP_URL_HOST);

        if (strtolower($domain) === strtolower($appDomain)) {
            return null;
        }

        $instanceId = Instance::where('domain', $domain)->first();
        $keyId = data_get($actorData, 'publicKey.id');
        $publicKey = data_get($actorData, 'publicKey.publicKeyPem');
        $sharedInbox = data_get($actorData, 'endpoints.sharedInbox');

        $res = InstanceActor::updateOrCreate(
            ['uri' => $actorData['id']],
            [
                'domain' => $domain,
                'key_id' => $keyId,
                'public_key' => $publicKey,
                'shared_inbox' => $sharedInbox,
                'instance_id' => $instanceId ? $instanceId->id : null,
                'last_fetched_at' => now(),
            ]
        );

        if (! $instanceId) {
            DiscoverInstance::dispatch($actorUrl)->onQueue('activitypub-in');
        }

        return $res;
    }

    protected function validateDeleteOrigin($actor): bool
    {
        if (! isset($this->activity['object'])) {
            Log::info('Delete rejected: missing object', ['activity_id' => $this->activity['id'] ?? null]);

            return false;
        }

        $objectUrl = is_array($this->activity['object'])
            ? data_get($this->activity, 'object.id')
            : data_get($this->activity, 'object');

        if (! $objectUrl) {
            Log::info('Delete rejected: no object url', ['activity_id' => $this->activity['id'] ?? null]);

            return false;
        }

        $actorDomain = is_object($actor)
            ? ($actor->domain ?: parse_url($actor->uri ?? '', PHP_URL_HOST))
            : null;

        if (! $actorDomain) {
            Log::warning('Delete rejected: no actor domain', [
                'activity_id' => $this->activity['id'] ?? null,
                'actor_class' => get_class($actor),
                'actor_uri' => ($actor->uri ?? null),
            ]);

            return false;
        }

        $objectDomain = parse_url($objectUrl, PHP_URL_HOST);

        $match = $objectDomain && strtolower($actorDomain) === strtolower($objectDomain);

        if (! $match) {
            Log::warning('Delete rejected: cross-domain origin mismatch', [
                'activity_id' => $this->activity['id'] ?? null,
                'actor_domain' => $actorDomain,
                'object_domain' => $objectDomain,
            ]);
        }

        return $match;
    }
}
