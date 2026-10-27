<?php

namespace App\Jobs\Federation;

use App\Federation\ActivityBuilders\UndoActivityBuilder;
use App\Models\Follower;
use App\Models\Profile;
use App\Services\SignedHttpClient;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class DeliverUndoFollowActivity implements ShouldQueue
{
    use Queueable;

    protected $followerId;

    protected $profileId;

    protected $followingId;

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
    public function __construct(Follower $follower)
    {
        $this->followerId = $follower->id;
        $this->profileId = $follower->profile_id;
        $this->followingId = $follower->following_id;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $actor = Profile::find($this->profileId);
        $target = Profile::find($this->followingId);

        if (! $actor || ! $target) {
            Log::warning('Cannot deliver Undo - profiles not found');

            return;
        }

        $activity = UndoActivityBuilder::buildForFollow($actor, $target->getActorId(), $this->followerId);

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
