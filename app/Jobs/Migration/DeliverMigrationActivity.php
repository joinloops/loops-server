<?php

namespace App\Jobs\Migration;

use App\Services\SignedHttpClient;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class DeliverMigrationActivity implements ShouldBeUnique, ShouldQueue
{
    use Batchable, Queueable;

    public $tries = 5;

    public $timeout = 60;

    public $uniqueFor = 3600;

    public function __construct(
        public string $keyId,
        public string $inboxUrl,
        public array $activity,
    ) {
        if (! parse_url($inboxUrl, PHP_URL_HOST)) {
            throw new \InvalidArgumentException("Invalid inbox URL: {$inboxUrl}");
        }

        $this->onQueue('activitypub-out');
    }

    public function uniqueId(): string
    {
        return 'deliver-migration-'.sha1(($this->activity['id'] ?? '').'|'.$this->inboxUrl);
    }

    public function backoff(): array
    {
        return [30, 120, 600, 1800];
    }

    public function handle(): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }

        $body = json_encode($this->activity, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $response = app(SignedHttpClient::class)->post($this->keyId, $this->inboxUrl, $body, (int) config('loops.federation.delivery.timeout', 10));

        if ($response->successful()) {
            return;
        }

        if ($response->clientError() && ! in_array($response->status(), [408, 429], true)) {
            Log::warning('Migration activity rejected by remote inbox', [
                'type' => $this->activity['type'] ?? null,
                'inbox' => $this->inboxUrl,
                'status' => $response->status(),
            ]);

            $this->delete();

            return;
        }

        throw new \RuntimeException("Migration activity delivery to {$this->inboxUrl} failed with HTTP {$response->status()}");
    }
}
