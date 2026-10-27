<?php

namespace App\Jobs\Federation;

use App\Federation\ActivityBuilders\CreateActivityBuilder;
use App\Models\Video;
use App\Services\SignedHttpClient;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class DeliverCreateVideoActivity implements ShouldBeUnique, ShouldQueue
{
    use Batchable, Queueable;

    public $video;

    public $inboxUrl;

    public $recipientProfileIds;

    private $parsedUrl;

    private $deliveryTimeout;

    public $tries = 3;

    public $timeout = 120;

    public $uniqueFor = 3600;

    public $deleteWhenMissingModels = true;

    public function uniqueId(): string
    {
        return "deliver-video-create-{$this->video->id}-{$this->inboxUrl}";
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

        if ($actor->local && $video->visibility == 1) {
            app(\App\Services\RelayService::class)->deliverToRelays($actor, $activity);
        }

        $parsedUrl = $this->parsedUrl;

        try {
            $response = app(SignedHttpClient::class)->post($actor->getKeyId(), $this->inboxUrl, $activity, (int) $this->deliveryTimeout);

            if ($response->successful()) {
                return;
            }

            if ($response->clientError() && ! in_array($response->status(), [408, 429])) {
                $this->delete();

                return;
            }

        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw $e;
        }
    }

    public function failed(\Throwable $exception) {}
}
