<?php

namespace App\Jobs\Federation;

use App\Federation\ActivityBuilders\DeleteActivityBuilder;
use App\Models\Profile;
use App\Services\SignedHttpClient;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class DeliverDeleteVideoActivity implements ShouldBeUnique, ShouldQueue
{
    use Batchable, Queueable;

    public $profile;

    public $videoObjectUrl;

    public $inboxUrl;

    public $recipientProfileIds;

    public $visibility;

    private $parsedUrl;

    private $deliveryTimeout;

    public $tries = 1;

    public $timeout = 30;

    public $uniqueFor = 3600;

    public $deleteWhenMissingModels = true;

    public function uniqueId(): string
    {
        return "deliver-video-delete-{$this->videoObjectUrl}-{$this->inboxUrl}";
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
    public function __construct(Profile $profile, string $inboxUrl, string $videoObjectUrl, array $recipientProfileIds = [], int $visibility = 1)
    {
        $this->profile = $profile;
        $this->inboxUrl = $inboxUrl;
        $this->recipientProfileIds = $recipientProfileIds;
        $this->videoObjectUrl = $videoObjectUrl;
        $this->visibility = $visibility;

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
        $actor = $this->profile;
        $inboxUrl = $this->inboxUrl;

        $activity = DeleteActivityBuilder::buildForVideo($actor, $this->videoObjectUrl, $this->visibility ?? 1);

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
