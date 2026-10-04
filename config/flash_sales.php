<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Redis key prefix
    |--------------------------------------------------------------------------
    |
    | Stock for a sale lives at {prefix}:{id}:stock. Buyers live in a hash at
    | {prefix}:{id}:buyers. Both keys are updated by one Lua script.
    |
    */

    'key_prefix' => 'flash',

];
