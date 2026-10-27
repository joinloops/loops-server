<?php

namespace App\Jobs\Federation;

use App\Models\Profile;
use App\Services\SignedHttpClient;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class DeliverUpdateProfileActivity implements ShouldBeUnique, ShouldQueue
{
    use Batchable, Queueable;

    public $profile;

    public $inboxUrl;

    public $activity;

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
        return 'deliver-profile-update-'.$this->profile->id.'-'.sha1((string) ($this->activity['id'] ?? '')).'-'.$this->inboxUrl;
    }

    public function backoff(): array
    {
        return [1, 10, 15];
    }

    public function __construct(Profile $profile, string $inboxUrl, array $activity, array $recipientProfileIds = [])
    {
        $this->profile = $profile;
        $this->inboxUrl = $inboxUrl;
        $this->activity = $activity;
        $this->recipientProfileIds = $recipientProfileIds;

        $this->parsedUrl = parse_url($inboxUrl);

        if (! $this->parsedUrl || ! isset($this->parsedUrl['host'])) {
            throw new \InvalidArgumentException("Invalid inbox URL: {$inboxUrl}");
        }

        $this->devLog = (bool) config('logging.dev_log');
        $this->deliveryTimeout = config('loops.federation.delivery.timeout', 10);
    }

    public function handle(): void
    {
        $actor = $this->profile;

        if (! $actor->local || $actor->status != 1) {
            $this->delete();

            return;
        }

        $payload = json_encode($this->activity, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $parsedUrl = $this->parsedUrl;

        $response = app(SignedHttpClient::class)->post($actor->getKeyId(), $this->inboxUrl, $payload, (int) $this->deliveryTimeout);

        if ($response->successful()) {
            return;
        }

        if ($response->clientError() && ! in_array($response->status(), [408, 429])) {
            $this->devLog && Log::warning('Profile update delivery permanently rejected', [
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
