<?php

return [
    'enabled' => env('LOOPS_CAPTCHA', false) && (env('TURNSTILE_SECRET_KEY') || env('HCAPTCHA_SECRET_KEY')) && in_array(env('LOOPS_CAPTCHA_DRIVER'), ['turnstile', 'hcaptcha']),

    'driver' => match (env('LOOPS_CAPTCHA_DRIVER')) {
        'hcaptcha' => 'hcaptcha',
        'turnstile' => 'turnstile',
        default => null,
    },

    'siteKey' => match (env('LOOPS_CAPTCHA_DRIVER')) {
        'hcaptcha' => env('HCAPTCHA_SITE_KEY'),
        'turnstile' => env('TURNSTILE_SITE_KEY'),
        default => null,
    },
];
