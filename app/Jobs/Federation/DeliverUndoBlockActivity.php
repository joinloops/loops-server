<?php

namespace App\Jobs\Federation;

use App\Federation\ActivityBuilders\UndoActivityBuilder;
use App\Models\Profile;
use App\Services\SignedHttpClient;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class DeliverUndoBlockActivity implements ShouldBeUnique, ShouldQueue
{
    use Batchable, Queueable;

    public $actor;

    public $targetProfile;

    public $originalBlockId;

    private $devLog;

    private $deliveryTimeout;

    public $tries = 3;

    public $timeout = 120;

    public $uniqueFor = 3600;

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

    public function uniqueId(): string
    {
        return "deliver-undo-block-{$this->actor->id}-{$this->targetProfile->id}";
    }

    /**
     * Create a new job instance.
     */
    public function __construct(Profile $actor, Profile $targetProfile, $originalBlockId)
    {
        $this->actor = $actor;
        $this->targetProfile = $targetProfile;
        $this->originalBlockId = $originalBlockId;

        $this->devLog = (bool) config('logging.dev_log');
        $this->deliveryTimeout = config('loops.federation.delivery.timeout', 10);
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $actor = $this->actor;
        $targetProfile = $this->targetProfile;
        $originalBlockId = $this->originalBlockId;

        if ($targetProfile->local) {
            $this->devLog && Log::info('Skipping Undo Block delivery for local target', [
                'actor' => $actor->id,
                'target' => $targetProfile->id,
            ]);
            $this->delete();

            return;
        }

        $inboxUrl = $targetProfile->inbox_url;

        if (! $inboxUrl) {
            $this->devLog && Log::warning('Target profile has no inbox URL, skipping Undo Block delivery', [
                'actor' => $actor->id,
                'target' => $targetProfile->id,
            ]);
            $this->delete();

            return;
        }

        $targetActorUrl = $targetProfile->uri ?: $targetProfile->getActorId();

        $activity = UndoActivityBuilder::buildForBlock($actor, $targetActorUrl, $originalBlockId);

        try {
            $response = app(SignedHttpClient::class)->post($actor->getKeyId(), $inboxUrl, $activity, (int) $this->deliveryTimeout);

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
