<?php

namespace App\Jobs\Federation;

use App\Models\Profile;
use App\Services\SignedHttpClient;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class DeliverCoauthorAuthorizationDelete implements ShouldBeUnique, ShouldQueue
{
    use Batchable, Queueable;

    public $tries = 3;

    public $timeout = 120;

    public $uniqueFor = 3600;

    public $deleteWhenMissingModels = true;

    public function __construct(
        public Profile $coauthor,
        public string $inboxUrl,
        public array $activity,
        public string $collabId,
    ) {}

    public function uniqueId(): string
    {
        return "deliver-coauthor-revoke-{$this->collabId}-{$this->inboxUrl}";
    }

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [1, 10, 15];
    }

    public function handle(): void
    {
        $devLog = (bool) config('logging.dev_log');

        if (! $this->coauthor->local) {
            $this->delete();

            return;
        }

        $response = app(SignedHttpClient::class)->post(
            $this->coauthor->getKeyId(),
            $this->inboxUrl,
            json_encode($this->activity, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            (int) config('loops.federation.delivery.timeout', 10)
        );

        if ($response->successful()) {
            return;
        }

        if ($response->clientError() && ! in_array($response->status(), [408, 429])) {
            $devLog && Log::warning('Coauthor revoke delivery permanently rejected', [
                'inbox' => $this->inboxUrl,
                'status' => $response->status(),
            ]);

            $this->delete();

            return;
        }

        $response->throw();
    }
}
