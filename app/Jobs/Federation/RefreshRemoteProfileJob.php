<?php

namespace App\Jobs\Federation;

use App\Models\Profile;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RefreshRemoteProfileJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;

    public $timeout = 60;

    public $uniqueFor = 900;

    public function uniqueId(): string
    {
        return 'refresh-remote-profile-'.sha1($this->actorUri);
    }

    public function backoff(): array
    {
        return [30, 120, 600];
    }

    public function __construct(
        public string $actorUri
    ) {}

    public function handle(): void
    {
        app(Profile::class)->findOrCreateFromUrl($this->actorUri, null, true);
    }
}
