<?php

namespace App\Jobs\Comment;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class CommentKlipyMediaShareTriggerJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 10;

    public array $backoff = [10, 60, 300];

    public function __construct(
        public string $slug,
        public string $type,
        public ?string $customerId = null,
    ) {
        $this->onQueue('klipy');
    }

    public function handle(): void
    {
        $apiKey = config('klipy.api_key');

        if (empty($apiKey)) {
            return;
        }

        $type = match ($this->type) {
            'memes' => 'static-memes',
            'gifs' => 'gifs',
            'stickers' => 'stickers',
            'clips' => 'clips',
            default => 'gifs',
        };

        $url = sprintf(
            'https://api.klipy.com/api/v1/%s/%s/share/%s',
            $apiKey,
            $type,
            $this->slug
        );

        $payload = $this->customerId
            ? ['customer_id' => $this->customerId]
            : [];

        $response = Http::timeout($this->timeout)
            ->acceptJson()
            ->asJson()
            ->post($url, $payload);

        if ($response->failed()) {
            if ($response->clientError()) {
                if (config('logging.dev_log')) {
                    Log::warning('Klipy share trigger client error', [
                        'slug' => $this->slug,
                        'type' => $this->type,
                        'status' => $response->status(),
                        'body' => $response->body(),
                    ]);
                }

                return;
            }

            $response->throw();
        }
    }

    public function failed(Throwable $e): void
    {
        if (config('logging.dev_log')) {

            Log::error('Klipy share trigger permanently failed', [
                'slug' => $this->slug,
                'type' => $this->type,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
