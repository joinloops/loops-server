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
        $instanceActor = $this->instanceActorService->get();

        $activity = [
            '@context' => 'https://www.w3.org/ns/activitystreams',
            'id' => route('activitypub.activity', ['id' => uniqid('follow-relay-', true)]),
            'type' => 'Follow',
            'actor' => $instanceActor->getActorId(),
            'object' => $relay->relay_actor_url ?? $relay->relay_url,
        ];

        $inboxUrl = $relay->getInbox();
        if (! $inboxUrl) {
            throw new Exception('Relay inbox URL not found');
        }

        $this->deliverActivity($instanceActor, $inboxUrl, $activity);
    }

    public function sendUndoFollowActivity(RelaySubscription $relay): void
    {
        $instanceActor = $this->instanceActorService->get();

        $followActivity = [
            'id' => route('activitypub.activity', ['id' => uniqid('follow-relay-', true)]),
            'type' => 'Follow',
            'actor' => $instanceActor->getActorId(),
            'object' => $relay->relay_actor_url ?? $relay->relay_url,
        ];

        $activity = [
            '@context' => 'https://www.w3.org/ns/activitystreams',
            'id' => route('activitypub.activity', ['id' => uniqid('undo-relay-', true)]),
            'type' => 'Undo',
            'actor' => $instanceActor->getActorId(),
            'object' => $followActivity,
        ];

        $inboxUrl = $relay->getInbox();
        if ($inboxUrl) {
            $this->deliverActivity($instanceActor, $inboxUrl, $activity);
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

                $this->deliverActivity($actor, $inboxUrl, $activity);
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
        $actorUrl = rtrim($relayUrl, '/').'/actor';

        $info = $this->activityPubService->get($actorUrl, [], false);

        if (! $info || ! is_array($info)) {
            throw new Exception('Failed to fetch relay information');
        }

        return $info;
    }

    protected function deliverActivity(Profile $actor, string $inboxUrl, array $activity): void
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
