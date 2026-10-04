<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Trusted proxies
    |--------------------------------------------------------------------------
    |
    | The production stack is reached only through Caddy. Trusting forwarded
    | headers lets rate limits use the visitor IP. Leave this off when PHP is
    | exposed directly.
    |
    */

    'trusted' => (bool) env('TRUST_PROXIES', false),

];
