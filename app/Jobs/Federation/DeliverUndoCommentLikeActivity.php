<?php

namespace App\Jobs\Federation;

use App\Federation\ActivityBuilders\UndoActivityBuilder;
use App\Models\Profile;
use App\Services\HttpSignatureService;
use App\Services\SigningService;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DeliverUndoCommentLikeActivity implements ShouldBeUnique, ShouldQueue
{
    use Batchable, Queueable;

    public $actor;

    public $target;

    public $objectUrl;

    public $commentLike;

    private $devLog;

    private $deliveryTimeout;

    public $backoff = [60, 300, 900];

    public $tries = 10;

    public $timeout = 120;

    public $uniqueFor = 3600;

    public function uniqueId(): string
    {
        return "deliver-undo-comment-like-{$this->commentLike}-{$this->actor->id}-{$this->objectUrl}";
    }

    /**
     * Create a new job instance.
     */
    public function __construct(Profile $actor, Profile $target, $commentLike, string $objectUrl)
    {
        $this->actor = $actor;
        $this->actor = $target;
        $this->commentLike = $commentLike;
        $this->objectUrl = $objectUrl;

        $this->devLog = (bool) config('logging.dev_log');
        $this->deliveryTimeout = config('loops.federation.delivery.timeout', 10);
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $actor = $this->actor;
        $target = $this->target;
        $inboxUrl = $target->inbox_url;

        $activity = UndoActivityBuilder::buildForLike($actor, $this->objectUrl, $this->commentLike);

        $parsedUrl = parse_url($inboxUrl);

        $headers = [
            'Host' => $parsedUrl['host'],
            'Date' => now()->toRfc7231String(),
            'Content-Type' => 'application/activity+json',
            'Accept' => 'application/activity+json',
            'User-Agent' => app('user_agent'),
        ];

        $signatureService = app(HttpSignatureService::class);

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
            $this->devLog && Log::error("Failed to sign request: {$e->getMessage()}", [
                'actor' => $actor->id,
                'inbox' => $inboxUrl,
            ]);

            $this->delete();

            return;
        }

        try {
            $response = Http::timeout($this->deliveryTimeout)
                ->withHeaders($headers)
                ->post($inboxUrl, $activity);

            if ($response->successful()) {
                $this->devLog && Log::info('Activity delivered successfully', [
                    'inbox' => $inboxUrl,
                    'status' => $response->status(),
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
            if ($this->devLog) {
                $error = "Network error: {$e->getMessage()}";
                Log::error($error, ['inbox' => $inboxUrl]);
            }
            throw $e;
        } catch (\Exception $e) {
            if ($this->devLog) {
                Log::error('Error delivering activity', [
                    'inbox' => $inboxUrl,
                    'error' => $e->getMessage(),
                ]);
            }
            throw $e;
        }
    }

    public function failed(\Throwable $exception)
    {
        Log::error('Activity delivery job failed permanently', [
            'id' => $this->commentLike,
            'type' => 'Undo',
            'error' => $exception->getMessage(),
        ]);
    }
}
