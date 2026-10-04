<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Payment driver
    |--------------------------------------------------------------------------
    |
    | fake never calls WeChat Pay or Stripe. http signs real API requests.
    | This repository has no merchant account or Stripe secret, so fake is
    | the default and the http driver is covered with Http::fake().
    |
    */

    'driver' => env('PAYMENTS_DRIVER', 'fake'),

    'webhook_tolerance_seconds' => (int) env('PAYMENTS_WEBHOOK_TOLERANCE', 300),

    'wechat' => [
        'app_id' => env('WECHAT_PAY_APP_ID', env('WECHAT_MINI_PROGRAM_APP_ID', '')),
        'mch_id' => env('WECHAT_PAY_MCH_ID', ''),
        'mch_serial' => env('WECHAT_PAY_MCH_SERIAL', ''),
        'private_key' => env('WECHAT_PAY_PRIVATE_KEY', ''),
        'api_v3_key' => env('WECHAT_PAY_API_V3_KEY', ''),
        'platform_public_key' => env('WECHAT_PAY_PLATFORM_PUBLIC_KEY', ''),
        'platform_serial' => env('WECHAT_PAY_PLATFORM_SERIAL', ''),
        'base_url' => rtrim((string) env('WECHAT_PAY_BASE_URL', 'https://api.mch.weixin.qq.com'), '/'),
        'notify_url' => env('WECHAT_PAY_NOTIFY_URL', ''),
    ],

    'stripe' => [
        'secret' => env('STRIPE_SECRET', ''),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET', ''),
        'base_url' => rtrim((string) env('STRIPE_BASE_URL', 'https://api.stripe.com'), '/'),
    ],

];
