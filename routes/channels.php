<?php

use App\Models\LiveChannel;
use App\Services\Live\LiveStreamService;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('live.{publicId}', function ($user, string $publicId) {
    $channel = LiveChannel::where('public_id', $publicId)->first();

    if (! $channel || ! $channel->is_enabled) {
        return false;
    }

    if (! $channel->current_stream_id) {
        return false;
    }

    if (app(LiveStreamService::class)->isBanned($channel, $user->profile_id)) {
        return false;
    }

    $profile = $user->profile;

    return [
        'id' => (string) $user->profile_id,
        'username' => $profile?->username,
        'name' => $profile?->name,
        'avatar' => $profile?->avatar,
        'is_host' => (int) $channel->profile_id === (int) $user->profile_id,
    ];
});

Broadcast::channel('live.{publicId}.host', function ($user, string $publicId) {
    $channel = LiveChannel::where('public_id', $publicId)->first();

    return $channel && (int) $channel->profile_id === (int) $user->profile_id;
});
