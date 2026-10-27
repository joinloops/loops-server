<?php

namespace App\Jobs\Federation;

use App\Federation\Signatures\IncomingSignature;
use App\Federation\Signatures\InvalidSignatureException;
use App\Models\Instance;
use App\Models\InstanceActor;
use App\Models\Profile;
use App\Services\ActivityPubService;
use App\Services\HttpSignatureService;
use App\Services\SanitizeService;
use App\Services\SignedHttpClient;
use App\Services\WebfingerService;
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
                return;
            }

            $verifiedActor = $verificationResult['actor'];

            if (($this->activity['type'] ?? null) === 'Delete') {
                if (! $this->validateDeleteOrigin($verifiedActor)) {
                    return;
                }
            }

            if (! ($verifiedActor instanceof Profile)) {
                $verifiedActor = isset($verifiedActor->uri)
                    ? Profile::where('uri', $verifiedActor->uri)->first()
                    : null;
            }

            if ($verifiedActor instanceof Profile && $verifiedActor->status !== 1) {
                return;
            }

            if ($this->isUserInbox && $this->targetActor) {
                ProcessInboxActivity::dispatch($this->activity, $verifiedActor, $this->targetActor)
                    ->onQueue('activitypub-in');
            } else {
                ProcessSharedInboxActivity::dispatch($this->activity, $verifiedActor)
                    ->onQueue('activitypub-in');
            }

        } catch (Exception $e) {
            throw $e;
        }
    }

    protected function verifySignature(
        HttpSignatureService $signatureService,
        ActivityPubService $activityPubService,
        SanitizeService $sanitizeService,
        WebfingerService $webfingerService
    ): array {
        try {
            $incoming = $signatureService->parseIncoming($this->headers, $this->method, $this->requestUri, $this->rawBody);
        } catch (InvalidSignatureException $e) {
            return ['valid' => false, 'reason' => $e->getMessage()];
        }

        $result = $this->resolveSignedActor($incoming, $activityPubService, $sanitizeService, $webfingerService);

        if ($result['valid'] && ($result['key_verified'] ?? true)) {
            app(SignedHttpClient::class)->noteInboundScheme($incoming->originDomain(), $incoming->scheme);
        }

        return $result;
    }

    protected function resolveSignedActor(
        IncomingSignature $incoming,
        ActivityPubService $activityPubService,
        SanitizeService $sanitizeService,
        WebfingerService $webfingerService
    ): array {
        $actorUrl = $incoming->actorUrl();
        $originDomain = parse_url($actorUrl, PHP_URL_HOST);

        if (! $sanitizeService->url($actorUrl, true)) {
            return ['valid' => false, 'reason' => 'Invalid actor URL'];
        }

        $profile = Profile::where('uri', $actorUrl)->first();
        if ($profile && $profile->public_key) {
            if ($incoming->verify($profile->public_key)) {
                return ['valid' => true, 'actor' => $profile];
            }
        }

        $instanceActor = InstanceActor::where('uri', $actorUrl)->first();
        if ($instanceActor && $instanceActor->public_key) {
            if ($incoming->verify($instanceActor->public_key)) {
                return ['valid' => true, 'actor' => $instanceActor];
            }
        }

        if ($originDomain && ! $instanceActor) {
            try {
                $webfingerActor = $webfingerService->findOrCreateRemoteInstanceActor("acct:{$originDomain}@{$originDomain}");
                if ($webfingerActor && $webfingerActor->public_key) {
                    if ($incoming->verify($webfingerActor->public_key)) {
                        return ['valid' => true, 'actor' => $webfingerActor];
                    }
                }
            } catch (Exception $e) {
                // Continue to fetch
            }
        }

        $actorData = $this->fetchActorData($actorUrl, $activityPubService);

        if (
            $actorData
            && ! isset($actorData['publicKey'])
            && isset($actorData['owner'])
            && is_string($actorData['owner'])
            && parse_url($actorData['owner'], PHP_URL_HOST) === $originDomain
        ) {
            $actorData = $this->fetchActorData($actorData['owner'], $activityPubService);
        }

        if (! $actorData || ! isset($actorData['publicKey']['publicKeyPem'])) {
            if (($this->activity['type'] ?? null) === 'Delete') {
                $existing = Profile::where('uri', $actorUrl)->first();

                if (! $existing) {
                    $stripped = preg_replace('#/(main-key|publickey|public-key)$#', '', $actorUrl);
                    if ($stripped !== $actorUrl) {
                        $existing = Profile::where('uri', $stripped)->first();
                    }
                }

                if ($existing) {
                    return ['valid' => true, 'actor' => $existing, 'key_verified' => false];
                }

                return ['valid' => true, 'actor' => (object) ['uri' => $actorUrl, 'domain' => $originDomain], 'key_verified' => false];
            }

            return ['valid' => false, 'reason' => 'Unable to fetch public key'];
        }

        $canonicalActorUrl = is_string($actorData['id'] ?? null) ? $actorData['id'] : $actorUrl;

        if (parse_url($canonicalActorUrl, PHP_URL_HOST) !== $originDomain) {
            return ['valid' => false, 'reason' => 'Actor id domain does not match keyId origin'];
        }

        if ($canonicalActorUrl !== $actorUrl) {
            $existing = Profile::where('uri', $canonicalActorUrl)->first();
            if ($existing && $existing->public_key) {
                if ($incoming->verify($existing->public_key)) {
                    return ['valid' => true, 'actor' => $existing];
                }
            }
        }

        $publicKey = $actorData['publicKey']['publicKeyPem'];

        if (! $incoming->verify($publicKey)) {
            return ['valid' => false, 'reason' => 'Signature verification failed with fresh key'];
        }

        $actor = $this->updateOrCreateActor($actorUrl, $actorData);

        return ['valid' => true, 'actor' => $actor ?? (object) ['uri' => $actorUrl, 'domain' => $originDomain]];
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

        $actorUrlSafe = app(SanitizeService::class)->url($actorUrl, true);

        if (! $actorUrlSafe) {
            return false;
        }

        if (! isset($actorData['inbox']) || empty($actorData['inbox'])) {
            return false;
        }

        $actorInbox = app(SanitizeService::class)->url($actorData['inbox'], true);
        if (! $actorInbox) {
            return false;
        }

        $sharedInbox = data_get($actorData, 'endpoints.sharedInbox');

        if ($sharedInbox) {
            $sharedInbox = app(SanitizeService::class)->url($sharedInbox, true);
        }

        $actorFollowers = null;
        if (isset($actorData['followers'])) {
            $actorFollowers = app(SanitizeService::class)->url($actorData['followers']);
        }

        $actorFollowing = null;
        if (isset($actorData['following'])) {
            $actorFollowing = app(SanitizeService::class)->url($actorData['following']);
        }

        $avatar = data_get($actorData, 'icon.url');

        if ($avatar) {
            $avatar = app(SanitizeService::class)->url($avatar);

            if (! $avatar) {
                return false;
            }
        }

        $remoteUrl = is_string(data_get($actorData, 'url')) ? data_get($actorData, 'url') : null;

        if ($remoteUrl) {
            $remoteUrl = app(SanitizeService::class)->url($remoteUrl);
            if (! $remoteUrl) {
                return false;
            }
        }

        $actorOutbox = null;
        if (isset($actorData['outbox'])) {
            $actorOutbox = app(SanitizeService::class)->url($actorData['outbox']);
        }

        $publicKey = null;
        if (isset($actorData['publicKey'], $actorData['publicKey']['publicKeyPem'])) {
            $publicKey = $actorData['publicKey']['publicKeyPem'];
            if (openssl_pkey_get_public($publicKey) === false) {
                return false;
            }
        }

        $attributes = [
            'username' => $acct,
            'name' => app(SanitizeService::class)->cleanPlainText($actorData['name'] ?? $username),
            'bio' => app(SanitizeService::class)->cleanHtmlWithSpacing($actorData['summary'] ?? null),
            'inbox_url' => $actorInbox,
            'avatar' => $avatar,
            'remote_url' => $remoteUrl,
            'outbox_url' => $actorOutbox ?? null,
            'followers_url' => $actorFollowers ?? null,
            'following_url' => $actorFollowing ?? null,
            'shared_inbox_url' => $sharedInbox ?? null,
            'public_key' => $publicKey,
            'manuallyApprovesFollowers' => data_get($actorData, 'manuallyApprovesFollowers', false),
            'last_fetched_at' => now(),
            'local' => false,
            'domain' => $domain,
        ];

        $existing = Profile::where('uri', $actorUrlSafe)
            ->orWhere('username', $acct)
            ->first();

        if ($existing) {
            $existing->fill(array_merge($attributes, ['uri' => $actorUrlSafe]));
            $existing->save();
            $res = $existing;
        } else {
            $res = Profile::create(array_merge($attributes, ['uri' => $actorUrlSafe]));
        }

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
            return false;
        }

        $objectUrl = is_array($this->activity['object'])
            ? data_get($this->activity, 'object.id')
            : data_get($this->activity, 'object');

        if (! $objectUrl) {
            return false;
        }

        $actorDomain = is_object($actor)
            ? ($actor->domain ?: parse_url($actor->uri ?? '', PHP_URL_HOST))
            : null;

        if (! $actorDomain) {
            return false;
        }

        $objectDomain = parse_url($objectUrl, PHP_URL_HOST);

        $match = $objectDomain && strtolower($actorDomain) === strtolower($objectDomain);

        return $match;
    }
}
