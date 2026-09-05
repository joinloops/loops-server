<?php

return [
    'enabled' => env('LIVE_ENABLED', false),

    'provider' => env('LIVE_PROVIDER', 'mediamtx'),

    'rtmp' => [
        'scheme' => env('LIVE_RTMP_SCHEME', 'rtmp'),
        'host' => env('LIVE_RTMP_HOST', 'live.example.org'),
        'port' => env('LIVE_RTMP_PORT', 1935),
        'app' => env('LIVE_RTMP_APP', 'live'),
        'user' => env('LIVE_RTMP_USER', 'live'),
    ],

    'playback' => [
        'base' => env('LIVE_PLAYBACK_BASE', 'https://live.example.org'),
    ],

    'mediamtx' => [
        'api' => env('LIVE_MEDIAMTX_API', 'http://127.0.0.1:9997'),
        'webhook_secret' => env('LIVE_WEBHOOK_SECRET'),
    ],

    'eligibility' => [
        'min_account_age_days' => env('LIVE_MIN_ACCOUNT_AGE_DAYS', 90),
        'min_followers' => env('LIVE_MIN_FOLLOWERS', 500),
    ],

    'stale_after_seconds' => env('LIVE_STALE_AFTER', 90),

    'chat' => [
        'buffer_size' => env('LIVE_CHAT_BUFFER', 50),
        'max_length' => env('LIVE_CHAT_MAX_LENGTH', 200),
        'rate_per_minute' => env('LIVE_CHAT_RATE_PER_MINUTE', 12),
    ],
];
