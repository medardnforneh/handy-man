<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Derived-fact cache TTL (seconds)
    |--------------------------------------------------------------------------
    |
    | How long a derived access fact (identity_verified, has_payout_method, …)
    | is cached before recomputation. Facts are also invalidated eagerly by the
    | events that change them (verification approved, payout added, skill
    | listed), so this is just a backstop — keep it modest.
    |
    */

    'fact_cache_ttl' => (int) env('ACCESS_FACT_CACHE_TTL', 300),

];
