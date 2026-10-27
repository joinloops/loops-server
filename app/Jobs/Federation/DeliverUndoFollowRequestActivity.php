<?php

namespace App\Jobs\Federation;

use App\Federation\ActivityBuilders\UndoActivityBuilder;
use App\Models\FollowRequest;
use App\Services\SignedHttpClient;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
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

        try {
            $response = app(SignedHttpClient::class)->post($actor->getKeyId(), $inboxUrl, $activity, (int) config('loops.federation.delivery.timeout', 10));

            if ($response->successful()) {
                $followRequest->delete();
            } else {
                $error = "Delivery failed with status {$response->status()}: {$response->body()}";

                if ($response->serverError() || in_array($response->status(), [408, 429])) {
                    throw new \Exception($error);
                }
            }
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            $error = "Network error: {$e->getMessage()}";
            throw $e;
        } catch (\Exception $e) {
            throw $e;
        }
    }

    public function failed(\Throwable $exception) {}
}
