<?php

namespace App\Jobs\Federation;

use App\Federation\ActivityBuilders\UndoActivityBuilder;
use App\Models\FollowRequest;
use App\Services\HttpSignatureService;
use App\Services\SigningService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DeliverUndoFollowRequestActivity implements ShouldQueue
{
    use Queueable;

    public $followRequest;

    public $tries = 3;

    public $timeout = 120;

    public $deleteWhenMissingModels = true;

    /**
     * Calculate the number of seconds to wait before retrying the job.
     *
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [1, 10, 15];
    }

    /**
     * Create a new job instance.
     */
    public function __construct(FollowRequest $followRequest)
    {
        $this->followRequest = $followRequest;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $followRequest = $this->followRequest;
        $actor = $followRequest->actor;
        $target = $followRequest->target;

        $activity = UndoActivityBuilder::buildForFollow($actor, $target->getActorId(), $followRequest->id);

        $inboxUrl = $target->inbox_url;
        $parsedUrl = parse_url($inboxUrl);

        if (! $parsedUrl || ! isset($parsedUrl['host'])) {
            $error = "Invalid inbox URL: {$inboxUrl}";
            Log::error($error);

            return;
        }

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
                $followRequest->delete();
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
            'to' => $this->followRequest->following_id,
            'type' => 'Follow',
            'error' => $exception->getMessage(),
        ]);
    }
}
