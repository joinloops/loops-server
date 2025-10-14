<?php

namespace App\Jobs\Federation;

use App\Models\Profile;
use App\Services\HttpSignatureService;
use App\Services\SigningService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DeliverRejectActivity implements ShouldQueue
{
    use Queueable;

    public $activity;

    public $actor;

    public $target;

    public $tries = 10;

    public $timeout = 120;

    /**
     * Create a new job instance.
     */
    public function __construct($activity, Profile $actor, Profile $target)
    {
        $this->activity = $activity;
        $this->actor = $actor;
        $this->target = $target;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $activity = $this->activity;
        $actor = $this->actor;
        $target = $this->target;

        $inboxUrl = $target->inbox_url;
        $parsedUrl = parse_url($inboxUrl);

        if (! $parsedUrl || ! isset($parsedUrl['host'])) {
            $error = "Invalid inbox URL: {$inboxUrl}";
            Log::error($error);

            return;
        }

        $signatureService = app(HttpSignatureService::class);

        $headers = [
            'Host' => $parsedUrl['host'],
            'Date' => now()->toRfc7231String(),
            'Content-Type' => 'application/activity+json',
            'Accept' => 'application/activity+json',
            'User-Agent' => app('user_agent'),
        ];

        try {
            $privateKey = app(SigningService::class)->getPrivateKey();
            $signature = $signatureService->sign(
                $actor->getKeyId(),
                $privateKey,
                $headers,
                'POST',
                $parsedUrl['path'] ?? '/',
                json_encode($activity)
            );

            $headers['Signature'] = $signature;
        } catch (\Exception $e) {
            $error = "Failed to sign request: {$e->getMessage()}";
            Log::error($error, ['actor' => $actor->id]);

            return;
        }

        try {
            $response = Http::timeout(config('loops.federation.delivery.timeout', 10))
                ->withHeaders($headers)
                ->post($inboxUrl, $activity);

            if ($response->successful()) {
            } else {
                $error = "Delivery failed with status {$response->status()}: {$response->body()}";
                Log::warning($error, [
                    'inbox' => $inboxUrl,
                    'status' => $response->status(),
                ]);

                if ($response->serverError() || in_array($response->status(), [408, 429])) {
                    throw new \Exception($error);
                }
            }
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            $error = "Network error: {$e->getMessage()}";
            Log::error($error, ['inbox' => $inboxUrl]);

            throw $e;
        } catch (\Exception $e) {
            Log::error('Error delivering activity', [
                'inbox' => $inboxUrl,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    public function failed(\Throwable $exception)
    {
        Log::error('Activity delivery job failed permanently', [
            'to' => $this->target->id,
            'type' => 'Reject',
            'error' => $exception->getMessage(),
        ]);
    }
}
