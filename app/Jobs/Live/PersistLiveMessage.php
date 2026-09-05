<?php

namespace App\Jobs\Live;

use App\Models\LiveStream;
use App\Models\LiveStreamMessage;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class PersistLiveMessage implements ShouldQueue
{
    use Queueable;

    public $tries = 2;

    public function __construct(
        public int $streamId,
        public int $profileId,
        public int $seq,
        public string $body
    ) {}

    public function handle(): void
    {
        LiveStreamMessage::updateOrCreate(
            ['live_stream_id' => $this->streamId, 'seq' => $this->seq],
            [
                'profile_id' => $this->profileId,
                'type' => LiveStreamMessage::TYPE_MESSAGE,
                'body' => $this->body,
            ]
        );

        LiveStream::where('id', $this->streamId)->increment('message_count');
    }
}
