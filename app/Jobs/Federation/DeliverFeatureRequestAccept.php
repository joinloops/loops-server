<?php

namespace App\Jobs\Federation;

use App\Federation\ActivityBuilders\FeatureRequestActivityBuilder;
use App\Models\Profile;
use App\Models\StarterKitAccount;
use App\Services\SignedHttpClient;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class DeliverFeatureRequestAccept implements ShouldBeUnique, ShouldQueue
{
    use Batchable, Queueable;

    public Profile $actor;

    public StarterKitAccount $account;

    public string $featureRequestId;

    private bool $devLog;

    private int $deliveryTimeout;

    public $tries = 3;

    public $timeout = 120;

    public $uniqueFor = 3600;

    public function uniqueId(): string
    {
        return "deliver-feature-request-accept-{$this->account->id}";
    }

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [1, 10, 15];
    }

    public function __construct(Profile $actor, StarterKitAccount $account, string $featureRequestId)
    {
        $this->actor = $actor;
        $this->account = $account->load('starterKit.profile');
        $this->featureRequestId = $featureRequestId;

        $this->devLog = (bool) config('logging.dev_log');
        $this->deliveryTimeout = config('loops.federation.delivery.timeout', 10);
    }

    public function handle(): void
    {
        $actor = $this->actor;
        $account = $this->account;

        if ($account->kit_status !== StarterKitAccount::STATUS_APPROVED) {
            $this->devLog && Log::info('Skipping Accept delivery — account no longer approved', [
                'account_id' => $account->id,
                'status' => $account->kit_status,
            ]);
            $this->delete();

            return;
        }

        $recipient = $account->starterKit->profile;

        if (! $recipient instanceof Profile) {
            $this->devLog && Log::warning('Skipping Accept delivery — recipient missing', [
                'account_id' => $account->id,
            ]);
            $this->delete();

            return;
        }

        if ($recipient->local) {
            $this->devLog && Log::warning('Skipping Accept delivery — recipient is local', [
                'account_id' => $account->id,
            ]);
            $this->delete();

            return;
        }

        $inboxUrl = $recipient->shared_inbox_url ?? $recipient->inbox_url;

        if (! $inboxUrl) {
            $this->devLog && Log::warning('Skipping Accept delivery — no inbox', [
                'recipient' => $recipient->id,
            ]);
            $this->delete();

            return;
        }

        $activity = FeatureRequestActivityBuilder::buildAccept(
            $actor,
            $recipient,
            $account,
            $this->featureRequestId,
        );

        try {
            $response = app(SignedHttpClient::class)->post($actor->getKeyId(), $inboxUrl, $activity, (int) $this->deliveryTimeout);

            if ($response->successful()) {
                $this->devLog && Log::info('FeatureRequest Accept delivered', [
                    'inbox' => $inboxUrl,
                    'status' => $response->status(),
                    'account_id' => $account->id,
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
            $this->devLog && Log::error("Network error: {$e->getMessage()}", ['inbox' => $inboxUrl]);
            throw $e;
        } catch (\Exception $e) {
            $this->devLog && Log::error('Error delivering Accept', [
                'inbox' => $inboxUrl,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    public function failed(\Throwable $exception)
    {
        Log::error('FeatureRequest Accept delivery failed permanently', [
            'account_id' => $this->account->id,
            'type' => 'Accept',
            'error' => $exception->getMessage(),
        ]);
    }
}
