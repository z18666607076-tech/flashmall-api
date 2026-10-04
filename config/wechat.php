<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Mini program client
    |--------------------------------------------------------------------------
    |
    | "fake" is the default for local development and tests. It derives a
    | stable openid from the login code and never calls WeChat. "http" calls
    | jscode2session and requires the app id and secret below.
    |
    */

    'driver' => env('WECHAT_DRIVER', 'fake'),

    'mini_program' => [
        'app_id' => env('WECHAT_MINI_PROGRAM_APP_ID'),
        'secret' => env('WECHAT_MINI_PROGRAM_SECRET'),
    ],

    'http' => [
        'base_url' => env('WECHAT_API_BASE_URL', 'https://api.weixin.qq.com'),
        'timeout' => 5,
    ],

];
