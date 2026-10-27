<?php

return [

    'paths' => [
        'api/*',
        'oauth/token',
        'oauth/revoke',
        '.well-known/nodeinfo',
        'nodeinfo/*',
        'sanctum/csrf-cookie',
    ],

    'allowed_methods' => ['*'],

    'allowed_origins' => ['*'],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => ['Link', 'ETag'],

    'max_age' => 86400,

    'supports_credentials' => false,

];
