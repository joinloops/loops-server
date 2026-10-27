<?php

namespace App\Jobs\Federation;

use App\Federation\ActivityBuilders\LikeActivityBuilder;
use App\Models\CommentLike;
use App\Models\Profile;
use App\Services\SignedHttpClient;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class DeliverCommentLikeActivity implements ShouldBeUnique, ShouldQueue
{
    use Batchable, Queueable;

    public $actor;

    public $target;

    public $objectUrl;

    public $commentLike;

    private $deliveryTimeout;

    public $tries = 3;

    public $timeout = 120;

    public $uniqueFor = 3600;

    public $deleteWhenMissingModels = true;

    public function uniqueId(): string
    {
        return "deliver-comment-like-{$this->commentLike->comment_id}-{$this->actor->id}-{$this->objectUrl}";
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
    public function __construct(Profile $actor, Profile $target, CommentLike $commentLike, string $objectUrl)
    {
        $this->actor = $actor;
        $this->target = $target;
        $this->commentLike = $commentLike;
        $this->objectUrl = $objectUrl;

        $this->deliveryTimeout = config('loops.federation.delivery.timeout', 10);
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $actor = $this->actor;
        $target = $this->target;
        $commentLike = $this->commentLike;
        $inboxUrl = $target->inbox_url;

        $activity = LikeActivityBuilder::buildForComment($actor, $this->objectUrl, $commentLike);

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
