<?php

namespace App\Jobs\Federation;

use App\Models\Profile;
use App\Services\HttpSignatureService;
use App\Services\SigningService;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DeliverDmActivity implements ShouldBeUnique, ShouldQueue
{
    use Batchable, Queueable;

    public $payload;

    public $inboxUrl;

    public $actorProfileId;

    private $parsedUrl;

    private $devLog;

    private $deliveryTimeout;

    public $tries = 5;

    public $timeout = 120;

    public $uniqueFor = 3600;

    public function uniqueId(): string
    {
        $activityId = is_string($this->payload['id'] ?? null)
            ? $this->payload['id']
            : md5((string) json_encode($this->payload));

        return 'deliver-dm-'.md5($activityId.'|'.$this->inboxUrl);
    }

    /**
     * Calculate the number of seconds to wait before retrying the job.
     *
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [10, 60, 600, 3600];
    }

    /**
     * Create a new job instance.
     */
    public function __construct(array $payload, string $inboxUrl, int $actorProfileId)
    {
        $this->payload = $payload;
        $this->inboxUrl = $inboxUrl;
        $this->actorProfileId = $actorProfileId;

        $this->parsedUrl = parse_url($inboxUrl);

        if (! $this->parsedUrl || ! isset($this->parsedUrl['host'])) {
            throw new \InvalidArgumentException("Invalid inbox URL: {$inboxUrl}");
        }

        $this->devLog = (bool) config('logging.dev_log');
        $this->deliveryTimeout = config('loops.federation.delivery.timeout', 10);

        $this->onQueue('activitypub-out');
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $actor = Profile::find($this->actorProfileId);

        if (! $actor || $actor->domain !== null) {
            $this->devLog && Log::warning('DM delivery skipped: actor missing or not local', [
                'actor_profile_id' => $this->actorProfileId,
                'inbox' => $this->inboxUrl,
            ]);

            $this->delete();

            return;
        }

        $parsedUrl = $this->parsedUrl;

        $headers = [
            'Host' => $parsedUrl['host'],
            'Date' => now()->toRfc7231String(),
            'Content-Type' => 'application/activity+json',
            'Accept' => 'application/activity+json',
            'User-Agent' => app('user_agent'),
        ];

        $signatureService = app(HttpSignatureService::class);
        $path = $parsedUrl['path'] ?? '/';
        $queryString = isset($parsedUrl['query']) ? '?'.$parsedUrl['query'] : '';
        $requestPath = $path.$queryString;

        try {
            $privateKey = app(SigningService::class)->getPrivateKey();
            $signature = $signatureService->sign(
                $actor->getKeyId(),
                $privateKey,
                $headers,
                'POST',
                $requestPath,
                json_encode($this->payload)
            );

            $headers['Signature'] = $signature;
        } catch (\Exception $e) {
            $this->devLog && Log::error("Failed to sign DM delivery: {$e->getMessage()}", [
                'actor' => $actor->id,
                'inbox' => $this->inboxUrl,
            ]);

            $this->delete();

            return;
        }

        $response = Http::timeout($this->deliveryTimeout)
            ->withHeaders($headers)
            ->post($this->inboxUrl, $this->payload);

        if ($response->successful()) {
            return;
        }

        if ($response->clientError() && ! in_array($response->status(), [408, 429])) {
            $this->devLog && Log::warning('DM delivery permanently rejected', [
                'inbox' => $this->inboxUrl,
                'status' => $response->status(),
            ]);

            $this->delete();

            return;
        }

        throw new \Exception("DM delivery to '{$this->inboxUrl}' failed with HTTP {$response->status()}.");
    }

    public function failed(\Throwable $exception): void
    {
        $this->devLog && Log::error('DM delivery failed permanently', [
            'inbox' => $this->inboxUrl,
            'error' => $exception->getMessage(),
        ]);
    }
}
