<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Product read cache
    |--------------------------------------------------------------------------
    |
    | Published product reads are stored in a taggable cache (Redis). Writes
    | flush the tag. Cache::touch() extends the TTL when a cached product is
    | read again.
    |
    */

    'cache_ttl' => (int) env('CATALOG_CACHE_TTL', 600),

    'cache_tag' => 'catalog',

];
