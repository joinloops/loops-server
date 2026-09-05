<?php

namespace App\Contracts;

use App\Models\LiveChannel;
use App\Models\LiveStream;

interface LiveProvider
{
    public function ingestFor(LiveChannel $channel): array;

    public function playbackUrl(LiveChannel $channel): string;

    public function isPublishing(LiveChannel $channel): bool;

    public function activePaths(): array;

    public function drop(LiveStream $stream): bool;
}
