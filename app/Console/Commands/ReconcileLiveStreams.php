<?php

namespace App\Console\Commands;

use App\Contracts\LiveProvider;
use App\Models\LiveStream;
use App\Services\Live\LiveStreamService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('live:reconcile')]
#[Description('End live streams that are no longer publishing on the media server')]
class ReconcileLiveStreams extends Command
{
    public function handle(LiveProvider $provider, LiveStreamService $service): int
    {
        if (! config('live.enabled')) {
            return self::SUCCESS;
        }

        $active = collect($provider->activePaths());
        $staleAfter = now()->subSeconds((int) config('live.stale_after_seconds'));
        $ended = 0;

        LiveStream::query()
            ->active()
            ->with('channel')
            ->chunkById(100, function ($streams) use ($active, $staleAfter, $service, &$ended) {
                foreach ($streams as $stream) {
                    $channel = $stream->channel;

                    if (! $channel) {
                        $service->endStream($stream, LiveStream::STATUS_FAILED);
                        $ended++;

                        continue;
                    }

                    if ($active->contains($channel->ingestPath())) {
                        $service->touch($stream);

                        continue;
                    }

                    if ($stream->last_seen_at && $stream->last_seen_at->gt($staleAfter)) {
                        continue;
                    }

                    $service->endStream($stream, LiveStream::STATUS_ENDED);
                    $ended++;
                }
            });

        $this->info("reconciled, ended {$ended}");

        return self::SUCCESS;
    }
}
