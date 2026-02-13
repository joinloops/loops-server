<?php

namespace App\Services;

use App\Models\Profile;
use App\Models\RelaySubscription;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class RelayService
{
    protected $activityPubService;

    protected $deliveryService;

    protected $instanceActorService;

    protected $signingService;

    public function __construct(
        ActivityPubService $activityPubService,
        DeliveryService $deliveryService,
        InstanceActorService $instanceActorService,
        SigningService $signingService
    ) {
        $this->activityPubService = $activityPubService;
        $this->deliveryService = $deliveryService;
        $this->instanceActorService = $instanceActorService;
        $this->signingService = $signingService;
    }

    public function subscribe(string $relayUrl): RelaySubscription
    {
        $relayInfo = $this->fetchRelayInfo($relayUrl);

        $relay = RelaySubscription::create([
            'relay_url' => $relayUrl,
            'relay_actor_url' => $relayInfo['id'] ?? null,
            'inbox_url' => $relayInfo['inbox'] ?? null,
            'shared_inbox_url' => $relayInfo['sharedInbox'] ?? $relayInfo['endpoints']['sharedInbox'] ?? null,
            'status' => 'pending',
            'relay_info' => $relayInfo,
        ]);

        $this->sendFollowActivity($relay);

        return $relay;
    }

    public function unsubscribe(RelaySubscription $relay): void
    {
        if ($relay->isActive()) {
            $this->sendUndoFollowActivity($relay);
        }

        $relay->delete();
    }

    public function sendFollowActivity(RelaySubscription $relay): void
    {
        $instanceActor = $this->instanceActorService;

        $activityId = $instanceActor->permalink('#follows/'.$relay->id);

        $activity = [
            '@context' => 'https://www.w3.org/ns/activitystreams',
            'id' => $activityId,
            'type' => 'Follow',
            'actor' => $instanceActor->getActorId(),
            'object' => $relay->relay_actor_url ?? $relay->relay_url,
        ];

        $inboxUrl = $relay->getInbox();
        if (! $inboxUrl) {
            throw new Exception('Relay inbox URL not found');
        }

        $this->deliverActivityAsInstance($inboxUrl, $activity);
    }

    public function sendUndoFollowActivity(RelaySubscription $relay): void
    {
        $instanceActor = $this->instanceActorService;

        $activityId = $instanceActor->permalink('#follows/'.$relay->id);

        $followActivity = [
            'id' => $activityId,
            'type' => 'Follow',
            'actor' => $instanceActor->getActorId(),
            'object' => $relay->relay_actor_url ?? $relay->relay_url,
        ];

        $activity = [
            '@context' => 'https://www.w3.org/ns/activitystreams',
            'id' => $activityId.'/undo',
            'type' => 'Undo',
            'actor' => $instanceActor->getActorId(),
            'object' => $followActivity,
        ];

        $inboxUrl = $relay->getInbox();
        if ($inboxUrl) {
            $this->deliverActivityAsInstance($inboxUrl, $activity);
        }
    }

    public function handleAccept(RelaySubscription $relay, array $activity): void
    {
        if ($relay->isPending()) {
            $relay->markAsActive();

            if (config('logging.dev_log')) {
                Log::info('Relay subscription accepted', ['relay' => $relay->relay_url]);
            }
        }
    }

    public function handleReject(RelaySubscription $relay): void
    {
        $relay->markAsRejected();

        if (config('logging.dev_log')) {
            Log::warning('Relay subscription rejected', ['relay' => $relay->relay_url]);
        }
    }

    public function deliverToRelays(Profile $actor, array $activity): void
    {
        $relays = RelaySubscription::where('status', 'active')
            ->where('send_public_posts', true)
            ->get();

        foreach ($relays as $relay) {
            try {
                $inboxUrl = $relay->getInbox();
                if (! $inboxUrl) {
                    continue;
                }

                // Deliver as the original actor, not the instance actor
                $this->deliverActivityAsUser($actor, $inboxUrl, $activity);
                $relay->incrementSent();

                if (config('logging.dev_log')) {
                    Log::debug('Activity delivered to relay', [
                        'relay' => $relay->relay_url,
                        'activity_type' => $activity['type'] ?? 'unknown',
                    ]);
                }
            } catch (Exception $e) {
                if (config('logging.dev_log')) {
                    Log::error('Failed to deliver to relay', [
                        'relay' => $relay->relay_url,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }
    }

    public function isFromRelay(string $actorUrl): ?RelaySubscription
    {
        return RelaySubscription::where('relay_actor_url', $actorUrl)
            ->where('status', 'active')
            ->where('receive_content', true)
            ->first();
    }

    protected function fetchRelayInfo(string $relayUrl): array
    {
        $cleanUrl = rtrim($relayUrl, '/');
        $parsed = parse_url($cleanUrl);
        $baseUrl = $parsed['scheme'].'://'.$parsed['host'];

        $candidates = [
            $cleanUrl,
        ];

        if (str_ends_with($cleanUrl, '/inbox')) {
            $withoutInbox = substr($cleanUrl, 0, -6);
            $candidates[] = $withoutInbox.'/actor';
            $candidates[] = $withoutInbox;
        }

        $candidates[] = $cleanUrl.'/actor';
        $candidates[] = $baseUrl.'/actor';
        $candidates[] = $baseUrl;

        $candidates = array_unique($candidates);

        foreach ($candidates as $actorUrl) {
            try {
                $info = $this->activityPubService->get($actorUrl, [], false);

                if ($info && is_array($info) && isset($info['id'])) {
                    return $info;
                }
            } catch (Exception $e) {
                continue;
            }
        }

        throw new Exception('Failed to fetch relay information from any known endpoint');
    }

    public function findRelayByActor(string $actorUrl): ?RelaySubscription
    {
        return RelaySubscription::where('relay_actor_url', $actorUrl)
            ->whereIn('status', ['active', 'pending'])
            ->first();
    }

    /**
     * Deliver activity signed by the instance actor
     */
    protected function deliverActivityAsInstance(string $inboxUrl, array $activity): void
    {
        $parsedUrl = parse_url($inboxUrl);

        if (! $parsedUrl || ! isset($parsedUrl['host'])) {
            throw new Exception('Invalid inbox URL');
        }

        $body = json_encode($activity);

        $headers = [
            'Host' => $parsedUrl['host'],
            'Date' => now()->toRfc7231String(),
            'Content-Type' => 'application/activity+json',
            'Accept' => 'application/activity+json',
            'User-Agent' => app('user_agent'),
        ];

        $privateKey = $this->signingService->getPrivateKey();
        $path = $parsedUrl['path'] ?? '/';
        $queryString = isset($parsedUrl['query']) ? '?'.$parsedUrl['query'] : '';
        $requestPath = $path.$queryString;

        $signature = app(HttpSignatureService::class)->sign(
            $this->instanceActorService->getKeyId(),
            $privateKey,
            $headers,
            'POST',
            $requestPath,
            $body
        );

        $headers['Signature'] = $signature;

        $response = Http::timeout(config('loops.federation.delivery.timeout', 10))
            ->withHeaders($headers)
            ->withBody($body, 'application/activity+json')
            ->post($inboxUrl);

        if (! $response->successful()) {
            throw new Exception("Delivery failed: {$response->status()} - {$response->body()}");
        }

        if (config('logging.dev_log')) {
            Log::debug('Delivered activity as instance actor', [
                'inbox' => $inboxUrl,
                'type' => $activity['type'] ?? 'unknown',
            ]);
        }
    }

    /**
     * Deliver activity signed by a user actor
     */
    protected function deliverActivityAsUser(Profile $actor, string $inboxUrl, array $activity): void
    {
        $parsedUrl = parse_url($inboxUrl);

        if (! $parsedUrl || ! isset($parsedUrl['host'])) {
            throw new Exception('Invalid inbox URL');
        }

        $body = json_encode($activity);

        $headers = [
            'Host' => $parsedUrl['host'],
            'Date' => now()->toRfc7231String(),
            'Content-Type' => 'application/activity+json',
            'Accept' => 'application/activity+json',
            'User-Agent' => app('user_agent'),
        ];

        $privateKey = $actor->private_key ?? $this->signingService->getPrivateKey();
        $path = $parsedUrl['path'] ?? '/';
        $queryString = isset($parsedUrl['query']) ? '?'.$parsedUrl['query'] : '';
        $requestPath = $path.$queryString;

        $signature = app(HttpSignatureService::class)->sign(
            $actor->getKeyId(),
            $privateKey,
            $headers,
            'POST',
            $requestPath,
            $body
        );

        $headers['Signature'] = $signature;

        $response = Http::timeout(config('loops.federation.delivery.timeout', 10))
            ->withHeaders($headers)
            ->withBody($body, 'application/activity+json')
            ->post($inboxUrl);

        if (! $response->successful()) {
            throw new Exception("Delivery failed: {$response->status()}");
        }
    }
}
