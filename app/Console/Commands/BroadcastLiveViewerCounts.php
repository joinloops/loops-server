<?php

namespace App\Console\Commands;

use App\Events\Live\ViewerCountUpdated;
use App\Models\LiveStream;
use App\Services\Live\LiveViewerService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('live:viewer-counts {--ticks=12} {--interval=5}')]
#[Description('Broadcast throttled viewer counts for active live streams')]
class BroadcastLiveViewerCounts extends Command
{
    public function handle(LiveViewerService $viewers): int
    {
        if (! config('live.enabled')) {
            return self::SUCCESS;
        }

        $ticks = max(1, (int) $this->option('ticks'));
        $interval = max(1, (int) $this->option('interval'));

        for ($i = 0; $i < $ticks; $i++) {
            $streams = LiveStream::query()->live()->with('channel')->get();

            foreach ($streams as $stream) {
                if (! $stream->channel) {
                    continue;
                }

                $count = $viewers->count($stream);

                if ($count > (int) $stream->peak_viewers) {
                    $stream->forceFill(['peak_viewers' => $count])->saveQuietly();
                }

                broadcast(new ViewerCountUpdated($stream->channel->public_id, $count));
            }

            if ($i < $ticks - 1) {
                sleep($interval);
            }
        }

        return self::SUCCESS;
    }
}
