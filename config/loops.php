<?php

return [
    'collabs' => [
        'enabled' => (bool) env('LOOPS_COLLABS_ENABLED', true),
        'request_ttl_days' => (int) env('LOOPS_COLLABS_REQUEST_TTL_DAYS', 7),
        'max_remote_coauthors' => (int) env('LOOPS_COLLABS_MAX_REMOTE', 3),
    ],

    'migration' => [
        'manifest_page_size' => (int) env('LOOPS_MIGRATION_MANIFEST_PAGE_SIZE', 25),
        'map_page_size' => (int) env('LOOPS_MIGRATION_MAP_PAGE_SIZE', 200),
        'intent_ttl_days' => (int) env('LOOPS_MIGRATION_INTENT_TTL_DAYS', 7),
        'job_timeout' => (int) env('LOOPS_MIGRATION_JOB_TIMEOUT', 900),
        'hard_max_video_mb' => (int) env('LOOPS_MIGRATION_HARD_MAX_VIDEO_MB', 512),
        'hard_max_duration' => (int) env('LOOPS_MIGRATION_HARD_MAX_DURATION', 900),
        'max_image_mb' => (int) env('LOOPS_MIGRATION_MAX_IMAGE_MB', 10),
        'max_follows' => (int) env('LOOPS_MIGRATION_MAX_FOLLOWS', 7500),
        'max_blocks' => (int) env('LOOPS_MIGRATION_MAX_BLOCKS', 1000),
        'max_bookmarks' => (int) env('LOOPS_MIGRATION_MAX_BOOKMARKS', 2000),
        'follow_spacing_seconds' => (int) env('LOOPS_MIGRATION_FOLLOW_SPACING', 1),
        'bookmark_spacing_seconds' => (int) env('LOOPS_MIGRATION_BOOKMARK_SPACING', 5),
        'video_codecs' => ['h264'],
        'audio_codecs' => ['aac', 'mp3', 'opus'],
        'containers' => ['mov', 'mp4', 'm4a', '3gp', '3g2', 'mj2'],
        'max_dimension' => (int) env('LOOPS_MIGRATION_MAX_DIMENSION', 4096),
    ],

    'media' => [
        'video_types' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('LOOPS_VIDEO_TYPES', 'mp4,mov,m4v'))
        ))),

        'transcode' => [
            'short_edge' => (int) env('LOOPS_TRANSCODE_SHORT_EDGE', 720),
            'crf' => (int) env('LOOPS_TRANSCODE_CRF', 23),
            'preset' => env('LOOPS_TRANSCODE_PRESET', 'slow'),
            'audio_kbps' => (int) env('LOOPS_TRANSCODE_AUDIO_KBPS', 128),
            'advanced' => (bool) env('LOOPS_TRANSCODE_ADVANCED', false),
            'fallback_preset' => env('LOOPS_TRANSCODE_FALLBACK_PRESET', 'veryfast'),
            'max_fps' => (int) env('LOOPS_TRANSCODE_MAX_FPS', 60),
            'job_timeout' => (int) env('LOOPS_TRANSCODE_JOB_TIMEOUT', 900),
            'retry_window_hours' => (int) env('LOOPS_TRANSCODE_RETRY_WINDOW_HOURS', 6),
            'temp_dir' => env('LOOPS_TRANSCODE_TEMP_DIR'),
        ],
    ],

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

    'emails' => [
        'support' => env('LOOPS_EMAILS_SUPPORT'),
        'dmca' => env('LOOPS_EMAILS_DMCA'),
    ],

    'feed' => [
        'fyp' => [
            'max_page' => [
                'enabled' => env('LOOPS_FEED_FYP_MAXP_ENABLED', false),
                'max_days' => env('LOOPS_FEED_FYP_MAXP_MAX_DAYS', 7),
            ],
        ],
        'fyf' => [
            'max_age_days' => env('LOOPS_FEED_FYF_MAX_AGE_DAYS', 730),
            'weights' => [
                'affinity' => env('LOOPS_FEED_FYF_WEIGHT_AFFINITY', 0.35),
                'engagement' => env('LOOPS_FEED_FYF_WEIGHT_ENGAGEMENT', 0.25),
                'quality' => env('LOOPS_FEED_FYF_WEIGHT_QUALITY', 0.20),
                'freshness' => env('LOOPS_FEED_FYF_WEIGHT_FRESHNESS', 0.20),
            ],
            'freshness_half_life_days' => env('LOOPS_FEED_FYF_FRESHNESS_HALF_LIFE_DAYS', 7),
            'mix' => [
                'trending' => env('LOOPS_FEED_FYF_MIX_TRENDING', 0.2),
                'explore' => env('LOOPS_FEED_FYF_MIX_EXPLORE', 0.1),
            ],
            'creator_cap' => env('LOOPS_FEED_FYF_CREATOR_CAP', 2),
            'recycle' => [
                'enabled' => env('LOOPS_FEED_FYF_RECYCLE_ENABLED', true),
                'after_days' => env('LOOPS_FEED_FYF_RECYCLE_AFTER_DAYS', 30),
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
        'feed' => [
            'cache_hours' => env('LOOPS_EXPLORE_PUBLIC_FEED_CACHE_HOURS_TTL', 45),
            'post_ttl_days' => env('LOOPS_EXPLORE_PUBLIC_FEED_POST_TTL_DAYS', 1000),
            'max_posts' => env('LOOPS_EXPLORE_PUBLIC_FEED_MAX_POSTS', 10),
        ],
        'tags' => [
            'min_likes' => [
                'guest' => env('LOOPS_EXPLORE_MIN_LIKES_GUEST', 10),
                'user' => env('LOOPS_EXPLORE_MIN_LIKES_USER', 3),
            ],
            'recency_tiers' => [
                ['hours' => 6,  'min_likes' => env('LOOPS_EXPLORE_TIERS_SIX_HOURS', 3)],
                ['hours' => 24, 'min_likes' => env('LOOPS_EXPLORE_TIERS_TWENTY_FOUR_HOURS', 5)],
                ['hours' => 72, 'min_likes' => env('LOOPS_EXPLORE_TIERS_SEVENTY_TWO_HOURS', 10)],
            ],
            'pagination' => [
                'admin' => [
                    'max_pages' => env('LOOPS_EXPLORE_PAGINATION_ADMIN_MAX_PAGES', 100),
                    'max_items' => env('LOOPS_EXPLORE_PAGINATION_ADMIN_MAX_ITEMS', 300),
                ],
                'user' => [
                    'max_pages' => env('LOOPS_EXPLORE_PAGINATION_USER_MAX_PAGES', 10),
                    'max_items' => env('LOOPS_EXPLORE_PAGINATION_USER_MAX_ITEMS', 120),
                ],
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
        'signatures' => [
            'default' => env('LOOPS_FED_SIGNATURES_DEFAULT', 'rfc9421'),
            'double_knock' => (bool) env('LOOPS_FED_SIGNATURES_DOUBLE_KNOCK', true),
            'rfc9421_ttl_days' => (int) env('LOOPS_FED_SIGNATURES_RFC9421_TTL_DAYS', 30),
            'cavage_ttl_days' => (int) env('LOOPS_FED_SIGNATURES_CAVAGE_TTL_DAYS', 7),
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

    'remote_search' => [
        'monthly_video_limit' => env('LOOPS_REMSEARCH_MONTHLY_VID_LIMIT', 30),
    ],

    'system_notifications' => [
        'enabled' => env('LOOPS_SYSTEM_NOTIFICATIONS_ENABLED', true),
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

    'relay' => [
        'fanout' => [
            'min_followers' => env('LOOPS_RELAY_MIN_FOLLOWERS') !== null
                ? (int) env('LOOPS_RELAY_MIN_FOLLOWERS')
                : null,

            'min_account_age_days' => env('LOOPS_RELAY_MIN_ACCOUNT_AGE_DAYS') !== null
                ? (int) env('LOOPS_RELAY_MIN_ACCOUNT_AGE_DAYS')
                : null,
        ],
    ],

    'dm' => [
        'groups' => [
            'max_participants' => env('LOOPS_DM_GROUP_CHAT_MAX_PARTICIPANTS', 12),
        ],

        'compose' => [
            'min_account_age_days' => env('LOOPS_DM_MINACCOUNTAGEDAYS', 90),
            'min_followers' => env('LOOPS_DM_FOLLOWERS', 10),
        ],
    ],

    'usernames' => [
        'changes' => [
            'enabled' => (bool) env('LOOPS_USERNAME_CHANGES_ENABLED', true),
            'limit' => (int) env('LOOPS_USERNAME_CHANGES_LIMIT', 3),
            'window_days' => (int) env('LOOPS_USERNAME_CHANGES_WINDOW_DAYS', 365),
            'cooldown_hours' => (int) env('LOOPS_USERNAME_CHANGES_COOLDOWN_HOURS', 24),
        ],
    ],

    'account_deletion' => [
        'grace_days' => (int) env('LOOPS_ACCOUNT_DELETE_GRACE_DAYS', 30),
        'job_budget_seconds' => (int) env('LOOPS_ACCOUNT_DELETE_JOB_BUDGET', 40),
        'fanout_chunk' => (int) env('LOOPS_ACCOUNT_DELETE_FANOUT_CHUNK', 500),
    ],
];
