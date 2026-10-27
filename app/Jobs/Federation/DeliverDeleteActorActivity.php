<?php

namespace App\Jobs\Federation;

use App\Services\SignedHttpClient;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class DeliverDeleteActorActivity implements ShouldBeUnique, ShouldQueue
{
    use Batchable, Queueable;

    public $tries = 2;

    public $timeout = 30;

    public $uniqueFor = 3600;

    public function __construct(
        public string $actorId,
        public string $keyId,
        public string $inboxUrl,
        public array $activity,
    ) {
        $host = parse_url($inboxUrl, PHP_URL_HOST);

        if (! $host) {
            throw new \InvalidArgumentException("Invalid inbox URL: {$inboxUrl}");
        }
    }

    public function uniqueId(): string
    {
        return 'deliver-actor-delete-'.sha1($this->actorId.'|'.$this->inboxUrl);
    }

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [30, 300];
    }

    public function handle(): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }

        $body = json_encode($this->activity, JSON_UNESCAPED_SLASHES);

        $response = app(SignedHttpClient::class)->post($this->keyId, $this->inboxUrl, $body, (int) config('loops.federation.delivery.timeout', 10));

        if ($response->successful()) {
            return;
        }

        if ($response->clientError() && ! in_array($response->status(), [408, 429])) {
            $this->delete();

            return;
        }

        throw new \RuntimeException("Actor Delete delivery to {$this->inboxUrl} failed with HTTP {$response->status()}");
    }

    public function failed(\Throwable $exception): void
    {
        if (config('logging.dev_log')) {
            Log::warning('Actor Delete delivery failed', [
                'actor' => $this->actorId,
                'inbox' => $this->inboxUrl,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
