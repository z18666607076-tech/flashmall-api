<?php

return [

    'api_per_minute' => (int) env('API_RATE_LIMIT_PER_MINUTE', 60),

    'login_per_minute' => (int) env('WECHAT_LOGIN_RATE_LIMIT_PER_MINUTE', 10),

    'checkout_per_minute' => (int) env('CHECKOUT_RATE_LIMIT_PER_MINUTE', 30),

    'flash_purchase_per_minute' => (int) env('FLASH_PURCHASE_RATE_LIMIT_PER_MINUTE', 30),

];
