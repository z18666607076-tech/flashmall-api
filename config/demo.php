<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Public demo
    |--------------------------------------------------------------------------
    |
    | When enabled, WeChat login and payments stay on the fake drivers even if
    | the HTTP drivers are set, catalog and fulfilment writes are rejected, and
    | the scheduler may wipe transactional data overnight.
    |
    */

    'enabled' => (bool) env('DEMO_MODE', false),

    'login_code' => env('DEMO_LOGIN_CODE', 'demo'),

    'reset_at' => env('DEMO_RESET_AT', '03:00'),

    'timezone' => env('DEMO_RESET_TIMEZONE', 'Asia/Shanghai'),

];
