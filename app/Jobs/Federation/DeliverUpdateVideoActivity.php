<?php

namespace App\Jobs\Federation;

use App\Federation\ActivityBuilders\UpdateActivityBuilder;
use App\Models\Video;
use App\Services\SignedHttpClient;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class DeliverUpdateVideoActivity implements ShouldBeUnique, ShouldQueue
{
    use Batchable, Queueable;

    public $video;

    public $inboxUrl;

    public $recipientProfileIds;

    private $parsedUrl;

    private $devLog;

    private $deliveryTimeout;

    public $tries = 3;

    public $timeout = 120;

    public $uniqueFor = 3600;

    public $deleteWhenMissingModels = true;

    public function uniqueId(): string
    {
        return "deliver-video-update-{$this->video->id}-{$this->video->updated_at->getTimestamp()}-{$this->inboxUrl}";
    }

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

        $activity = UpdateActivityBuilder::buildForVideo($actor, $video);

        $payload = json_encode($activity, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $parsedUrl = $this->parsedUrl;

        $response = app(SignedHttpClient::class)->post($actor->getKeyId(), $this->inboxUrl, $payload, (int) $this->deliveryTimeout);

        if ($response->successful()) {
            return;
        }

        if ($response->clientError() && ! in_array($response->status(), [408, 429])) {
            $this->devLog && Log::warning('Update delivery permanently rejected', [
                'inbox' => $this->inboxUrl,
                'status' => $response->status(),
                'body' => substr($response->body(), 0, 500),
            ]);

            $this->delete();

            return;
        }

        $response->throw();
    }

    public function failed(\Throwable $exception) {}
}
