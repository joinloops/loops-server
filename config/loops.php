<?php

return [
    'api' => [
        'rate_limits' => [
            'enabled' => (bool) env('LOOPS_API_RATE_LIMITS_ENABLED', true),
            'guests' => [
                'per_minute' => (int) env('LOOPS_API_RATE_LIMITS_GUEST_PER_MIN', 60),
                'per_hour' => (int) env('LOOPS_API_RATE_LIMITS_GUEST_PER_HOUR', 700),
            ],
            'users' => [
                'per_minute' => (int) env('LOOPS_API_RATE_LIMITS_USER_PER_MIN', 120),
                'per_hour' => (int) env('LOOPS_API_RATE_LIMITS_USER_PER_HOUR', 3000),
            ],
        ],
    ],

    'feed' => [
        'fyp' => [
            'max_page' => [
                'enabled' => env('LOOPS_FEED_FYP_MAXP_ENABLED', false),
                'max_days' => env('LOOPS_FEED_FYP_MAXP_MAX_DAYS', 7),
            ],
        ],
        'following' => [
            'max_page' => [
                'enabled' => env('LOOPS_FEED_FOLLOWING_MAXP_ENABLED', true),
                'max_days' => env('LOOPS_FEED_FOLLOWING_MAXP_MAX_DAYS', 31),
            ],
        ],
    ],

    'explore' => [
        'tags' => [
            'min_likes' => [
                'guest' => env('LOOPS_EXPLORE_MIN_LIKES_GUEST', 10),
                'user' => env('LOOPS_EXPLORE_MIN_LIKES_USER', 10),
            ],
        ],
    ],

    'reports' => [
        'rate_limits' => [
            'admin_exempt' => env('LOOPS_REPORT_ADMIN_EX_LIMIT', true),
            'daily' => env('LOOPS_REPORT_DAILY_LIMIT', 15),
            'monthly' => env('LOOPS_REPORT_MONTHLY_LIMIT', 200),
        ],
    ],

    'uploads' => [
        'rate_limits' => [
            'per_day' => env('LOOPS_UPLOADS_MAX_USER_PER_DAY', false),
        ],
    ],

    'federation' => [
        'delivery' => [
            'timeout' => env('LOOPS_FED_DELIVERY_TIMEOUT', 10),
        ],
        'inbox_dispatch_chunk_size' => env('LOOPS_FED_INBOX_DIS_CHUNKER', 100),
        'inbox_max_followers' => env('LOOPS_FED_INBOX_MAX_FOLLOWERS', 5000),
        'cache_ttl' => env('LOOPS_FEDI_CACHE_TTL', 3600),
    ],

    'registration' => [
        'min_years_old' => env('LOOPS_REG_MIN_YEARS_OLD', 16),
        'max_resend_email_verify' => (int) env('LOOPS_REG_MAX_RESEND_EMAIL_VERIFY', 2),
    ],

    'autolinker' => [
        'mentions' => [
            // If true, remove_domain takes precedence over hide_domain
            'remove_domain' => env('LOOPS_AL_MEN_REMOVE_DOMAINS', true),
            'hide_domain' => env('LOOPS_AL_MEN_HIDE_DOMAINS', false),
            'target_blank' => env('LOOPS_AL_MEN_TARGET_BLANK', false),
            'max_length' => env('LOOPS_AL_MEN_MAX_LENGTH', 64),
        ],
    ],

    'backups' => [
        'enabled' => env('LOOPS_BACKUPS_ENABLED', false),
    ],

    'admin_dashboard' => [
        'autoUpdate' => (bool) env('LOOPS_ADMIN_DASHBOARD_AUTOUPDATE', true),
    ],

    'admin_mails' => [
        'to' => env('LOOPS_ADMIN_MAILS_TO'),
        'reports' => (bool) env('LOOPS_ADMIN_MAILS_REPORTS', false),
    ],

    'health' => [
        'enabled' => env('LOOPS_HEALTH_ENDPOINT_ENABLED', false),
        'secret' => env('LOOPS_HEALTH_ENDPOINT_SECRET'),
    ],

    'chromaprint' => [
        'enabled' => env('LOOPS_CHROMAPRINT_ENABLED', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Local Domains (Same-Server Instances)
    |--------------------------------------------------------------------------
    |
    | Comma-separated list of domains hosted on the same server that should
    | be allowed to federate even if they resolve to localhost/private IPs.
    | Useful for multi-instance setups on a single server.
    |
    */
    'local_domains' => env('LOOPS_LOCAL_DOMAINS', ''),
];
