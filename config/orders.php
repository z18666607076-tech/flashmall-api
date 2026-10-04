<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Unpaid order lifetime
    |--------------------------------------------------------------------------
    |
    | Checkout moves stock into a pending_payment order. A delayed job cancels
    | the order and restores stock when this many seconds pass without payment.
    | The next milestone's payment webhook is what marks the order paid.
    |
    */

    'unpaid_ttl_seconds' => (int) env('ORDER_UNPAID_TTL_SECONDS', 900),

];
