<?php

namespace App\Jobs\Federation;

use App\Models\Profile;
use App\Services\SignedHttpClient;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class DeliverFeatureRequestRevoke implements ShouldBeUnique, ShouldQueue
{
    use Batchable, Queueable;

    public Profile $actor;

    public Profile $recipient;

    public string $attestationUrl;

    public string $kitObjectUrl;

    public string $accountKey;

    private bool $devLog;

    private int $deliveryTimeout;

    public $tries = 3;

    public $timeout = 120;

    public $uniqueFor = 3600;

    public function uniqueId(): string
    {
        return "deliver-feature-request-revoke-{$this->accountKey}";
    }

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [1, 10, 15];
    }

    public function __construct(
        Profile $actor,
        Profile $recipient,
        string $attestationUrl,
        string $kitObjectUrl,
        string $accountKey,
    ) {
        $this->actor = $actor;
        $this->recipient = $recipient;
        $this->attestationUrl = $attestationUrl;
        $this->kitObjectUrl = $kitObjectUrl;
        $this->accountKey = $accountKey;

        $this->devLog = (bool) config('logging.dev_log');
        $this->deliveryTimeout = config('loops.federation.delivery.timeout', 10);
    }

    public function handle(): void
    {
        $actor = $this->actor;
        $recipient = $this->recipient;

        if ($recipient->local) {
            $this->devLog && Log::warning('Skipping Revoke delivery — recipient is local', [
                'recipient' => $recipient->id,
            ]);
            $this->delete();

            return;
        }

        $inboxUrl = $recipient->shared_inbox_url ?? $recipient->inbox_url;

        if (! $inboxUrl) {
            $this->devLog && Log::warning('Skipping Revoke delivery — no inbox', [
                'recipient' => $recipient->id,
            ]);
            $this->delete();

            return;
        }

        $activity = [
            '@context' => 'https://www.w3.org/ns/activitystreams',
            'id' => url('/ap/users/'.$actor->id.'/feature-requests/'.$this->accountKey.'/revoke'),
            'type' => 'Delete',
            'actor' => $actor->getActorId(),
            'to' => $recipient->uri,
            'object' => [
                'id' => $this->attestationUrl,
                'type' => 'FeatureAuthorization',
                'interactingObject' => $this->kitObjectUrl,
                'interactionTarget' => $actor->getActorId(),
            ],
        ];

        try {
            $response = app(SignedHttpClient::class)->post($actor->getKeyId(), $inboxUrl, $activity, (int) $this->deliveryTimeout);

            if ($response->successful()) {
                $this->devLog && Log::info('FeatureRequest Revoke delivered', [
                    'inbox' => $inboxUrl,
                    'status' => $response->status(),
                    'account_key' => $this->accountKey,
                ]);

                return;
            }

            if ($response->clientError() && ! in_array($response->status(), [408, 429])) {
                $this->devLog && Log::warning("Permanent client error {$response->status()}", [
                    'inbox' => $inboxUrl,
                ]);
                $this->delete();

                return;
            }

            throw new \Exception("Delivery failed with status {$response->status()}");
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            $this->devLog && Log::error("Network error: {$e->getMessage()}", ['inbox' => $inboxUrl]);
            throw $e;
        } catch (\Exception $e) {
            $this->devLog && Log::error('Error delivering Revoke', [
                'inbox' => $inboxUrl,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    public function failed(\Throwable $exception)
    {
        Log::error('FeatureRequest Revoke delivery failed permanently', [
            'account_key' => $this->accountKey,
            'error' => $exception->getMessage(),
        ]);
    }
}
