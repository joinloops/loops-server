<?php

namespace App\Jobs\Federation;

use App\Federation\ActivityBuilders\LikeActivityBuilder;
use App\Models\CommentReplyLike;
use App\Models\Profile;
use App\Services\SignedHttpClient;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class DeliverCommentReplyLikeActivity implements ShouldBeUnique, ShouldQueue
{
    use Batchable, Queueable;

    public $actor;

    public $target;

    public $objectUrl;

    public $commentReplyLike;

    private $deliveryTimeout;

    public $tries = 3;

    public $timeout = 120;

    public $uniqueFor = 3600;

    public $deleteWhenMissingModels = true;

    public function uniqueId(): string
    {
        return "deliver-comment-reply-like-{$this->commentReplyLike->comment_id}-{$this->actor->id}-{$this->objectUrl}";
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
    public function __construct(Profile $actor, Profile $target, CommentReplyLike $commentReplyLike, string $objectUrl)
    {
        $this->actor = $actor;
        $this->target = $target;
        $this->commentReplyLike = $commentReplyLike;
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
        $commentReplyLike = $this->commentReplyLike;
        $inboxUrl = $target->inbox_url;

        $activity = LikeActivityBuilder::buildForCommentReply($actor, $this->objectUrl, $commentReplyLike);

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
