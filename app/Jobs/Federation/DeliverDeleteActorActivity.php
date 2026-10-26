<?php

namespace App\Jobs\Federation;

use App\Services\HttpSignatureService;
use App\Services\SigningService;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
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

        $parsedUrl = parse_url($this->inboxUrl);
        $body = json_encode($this->activity, JSON_UNESCAPED_SLASHES);
        $devLog = (bool) config('logging.dev_log');

        $headers = [
            'Host' => $parsedUrl['host'].(isset($parsedUrl['port']) ? ':'.$parsedUrl['port'] : ''),
            'Date' => now()->toRfc7231String(),
            'Content-Type' => 'application/activity+json',
            'Accept' => 'application/activity+json',
            'User-Agent' => app('user_agent'),
        ];

        $path = ($parsedUrl['path'] ?? '/').(isset($parsedUrl['query']) ? '?'.$parsedUrl['query'] : '');

        try {
            $signature = app(HttpSignatureService::class)->sign(
                $this->keyId,
                app(SigningService::class)->getPrivateKey(),
                $headers,
                'POST',
                $path,
                $body
            );

            $headers['Signature'] = $signature;
        } catch (\Exception $e) {
            $devLog && Log::error('Failed to sign actor Delete', [
                'actor' => $this->actorId,
                'inbox' => $this->inboxUrl,
                'error' => $e->getMessage(),
            ]);

            $this->delete();

            return;
        }

        $response = Http::timeout((int) config('loops.federation.delivery.timeout', 10))
            ->withHeaders($headers)
            ->withBody($body, 'application/activity+json')
            ->post($this->inboxUrl);

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
