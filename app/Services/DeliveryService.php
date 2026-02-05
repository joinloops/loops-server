<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DeliveryService
{
    protected $signatureService;

    public function __construct(HttpSignatureService $signatureService)
    {
        $this->signatureService = $signatureService;
    }

    public function deliverToInbox($actor, $target, $activity)
    {
        $inboxUrl = $target->inbox_url;
        $parsedUrl = parse_url($inboxUrl);

        if (! $parsedUrl || ! isset($parsedUrl['host'])) {
            $error = "Invalid inbox URL: {$inboxUrl}";
            if (config('logging.dev_log')) {
                Log::error($error);
            }

            return;
        }

        $body = json_encode($activity);

        $headers = [
            'Host' => $parsedUrl['host'],
            'Date' => now()->toRfc7231String(),
            'Content-Type' => 'application/activity+json',
            'Accept' => 'application/activity+json',
            'User-Agent' => app('user_agent'),
        ];

        try {
            $privateKey = app(SigningService::class)->getPrivateKey();
            $path = $parsedUrl['path'] ?? '/';
            $queryString = isset($parsedUrl['query']) ? '?'.$parsedUrl['query'] : '';
            $requestPath = $path.$queryString;

            $signature = $this->signatureService->sign(
                $actor->getKeyId(),
                $privateKey,
                $headers,
                'POST',
                $requestPath,
                $body
            );

            $headers['Signature'] = $signature;
        } catch (\Exception $e) {
            $error = "Failed to sign request: {$e->getMessage()}";
            if (config('logging.dev_log')) {
                Log::error($error, ['actor' => $actor->id]);
            }

            return;
        }

        try {
            $response = Http::timeout(config('loops.federation.delivery.timeout', 10))
                ->withHeaders($headers)
                ->withBody($body, 'application/activity+json')
                ->post($inboxUrl);

            if ($response->successful()) {
                if (config('logging.dev_log')) {
                    Log::info('Successfully delivered activity', [
                        'type' => $activity['type'] ?? 'unknown',
                        'to' => $inboxUrl,
                        'status' => $response->status(),
                    ]);
                }
            } else {
                $error = "Delivery failed with status {$response->status()}: {$response->body()}";
                if (config('logging.dev_log')) {
                    Log::warning($error, [
                        'inbox' => $inboxUrl,
                        'status' => $response->status(),
                    ]);
                }

                if ($response->serverError() || in_array($response->status(), [408, 429])) {
                    throw new \Exception($error);
                }
            }
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            $error = "Network error: {$e->getMessage()}";
            if (config('logging.dev_log')) {
                Log::error($error, ['inbox' => $inboxUrl]);
            }
            throw $e;
        } catch (\Exception $e) {
            if (config('logging.dev_log')) {
                Log::error('Error delivering activity', [
                    'inbox' => $inboxUrl,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }
            throw $e;
        }
    }
}
