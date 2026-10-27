<?php

namespace App\Jobs\Federation;

use App\Models\Profile;
use App\Services\SignedHttpClient;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class DeliverStarterKitActivity implements ShouldBeUnique, ShouldQueue
{
    use Batchable, Queueable;

    public Profile $actor;

    public string $inboxUrl;

    /** @var array<string, mixed> */
    public array $activity;

    public string $uniqueKey;

    private bool $devLog;

    private int $deliveryTimeout;

    public $tries = 3;

    public $timeout = 60;

    public $uniqueFor = 3600;

    public function uniqueId(): string
    {
        return "deliver-starter-kit-{$this->uniqueKey}";
    }

    /** @return array<int, int> */
    public function backoff(): array
    {
        return [1, 10, 30];
    }

    /** @param array<string, mixed> $activity */
    public function __construct(Profile $actor, string $inboxUrl, array $activity, string $uniqueKey)
    {
        $this->actor = $actor;
        $this->inboxUrl = $inboxUrl;
        $this->activity = $activity;
        $this->uniqueKey = $uniqueKey;

        $this->devLog = (bool) config('logging.dev_log');
        $this->deliveryTimeout = config('loops.federation.delivery.timeout', 10);
    }

    public function handle(): void
    {
        $parsedUrl = parse_url($this->inboxUrl);

        if (! $parsedUrl || ! isset($parsedUrl['host'])) {
            $this->devLog && Log::warning('Invalid inbox URL', ['inbox' => $this->inboxUrl]);
            $this->delete();

            return;
        }

        try {
            $response = app(SignedHttpClient::class)->post($this->actor->getKeyId(), $this->inboxUrl, $this->activity, (int) $this->deliveryTimeout);

            if ($response->successful()) {
                $this->devLog && Log::info('Starter kit activity delivered', [
                    'inbox' => $this->inboxUrl,
                    'type' => $this->activity['type'] ?? 'unknown',
                    'status' => $response->status(),
                ]);

                return;
            }

            if ($response->clientError() && ! in_array($response->status(), [408, 429])) {
                $this->devLog && Log::warning("Permanent client error {$response->status()}", [
                    'inbox' => $this->inboxUrl,
                ]);
                $this->delete();

                return;
            }

            throw new \Exception("Delivery failed with status {$response->status()}");
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            throw $e;
        } catch (\Exception $e) {
            $this->devLog && Log::error('Error delivering starter kit activity', [
                'inbox' => $this->inboxUrl,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('DeliverStarterKitActivity failed permanently', [
            'inbox' => $this->inboxUrl,
            'unique_key' => $this->uniqueKey,
            'error' => $exception->getMessage(),
        ]);
    }
}
