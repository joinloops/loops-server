<?php

namespace App\Jobs\Federation;

use App\Federation\ActivityBuilders\CreateActivityBuilder;
use App\Models\Video;
use App\Services\HttpSignatureService;
use App\Services\SigningService;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DeliverCreateVideoActivity implements ShouldBeUnique, ShouldQueue
{
    use Batchable, Queueable;

    public $video;

    public $inboxUrl;

    public $recipientProfileIds;

    private $parsedUrl;

    private $devLog;

    private $deliveryTimeout;

    public $backoff = [60, 300, 900];

    public $tries = 10;

    public $timeout = 120;

    public $uniqueFor = 3600;

    public function uniqueId(): string
    {
        return "deliver-video-create-{$this->video->id}-{$this->inboxUrl}";
    }

    /**
     * Create a new job instance.
     */
    public function __construct(Video $video, string $inboxUrl, array $recipientProfileIds = [])
    {
        $this->video = $video->load('profile');
        $this->inboxUrl = $inboxUrl;
        $this->recipientProfileIds = $recipientProfileIds;

        $this->parsedUrl = parse_url($inboxUrl);

        if (! $this->parsedUrl || ! isset($this->parsedUrl['host'])) {
            throw new \InvalidArgumentException("Invalid inbox URL: {$inboxUrl}");
        }

        $this->devLog = (bool) config('logging.dev_log');
        $this->deliveryTimeout = config('loops.federation.delivery.timeout', 10);
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $video = $this->video;
        $actor = $video->profile;
        $inboxUrl = $this->inboxUrl;

        $activity = CreateActivityBuilder::buildForVideo($actor, $video);

        $parsedUrl = $this->parsedUrl;

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
                'inbox' => $this->inboxUrl,
            ]);

            $this->delete();

            return;
        }

        try {
            $response = Http::timeout($this->deliveryTimeout)
                ->withHeaders($headers)
                ->post($this->inboxUrl, $activity);

            if ($response->successful()) {
                $this->devLog && Log::info('Activity delivered successfully', [
                    'inbox' => $this->inboxUrl,
                    'recipients' => count($this->recipientProfileIds),
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
            'inbox' => $this->inboxUrl,
            'recipients' => count($this->recipientProfileIds),
            'type' => 'Create',
            'error' => $exception->getMessage(),
        ]);
    }
}
