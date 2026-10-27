<?php

namespace App\Jobs\Federation;

use App\Federation\ActivityBuilders\LikeActivityBuilder;
use App\Models\Profile;
use App\Models\Video;
use App\Models\VideoLike;
use App\Services\SignedHttpClient;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class DeliverVideoLikeActivity implements ShouldBeUnique, ShouldQueue
{
    use Batchable, Queueable;

    public $actor;

    public $video;

    public $videoLike;

    private $deliveryTimeout;

    public $tries = 3;

    public $timeout = 120;

    public $uniqueFor = 3600;

    public function uniqueId(): string
    {
        return "deliver-video-like-{$this->video->id}-{$this->actor->id}-{$this->videoLike->id}";
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
    public function __construct(Profile $actor, Video $video, VideoLike $videoLike)
    {
        $this->actor = $actor;
        $this->video = $video->load('profile');
        $this->videoLike = $videoLike;

        $this->deliveryTimeout = config('loops.federation.delivery.timeout', 10);
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $actor = $this->actor;
        $video = $this->video;
        $videoLike = $this->videoLike;
        $profile = $video->profile;
        $inboxUrl = $profile->inbox_url;

        $activity = LikeActivityBuilder::buildForVideo($actor, $video, $videoLike);

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
