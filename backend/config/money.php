<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Platform commission
    |--------------------------------------------------------------------------
    | Taken from each escrow release, in basis points (1500 = 15%, doc 03). The provider is credited
    | the remainder. Kept in config so it can vary by market/category later without touching the
    | money code.
    */
    'commission_bps' => (int) env('MONEY_COMMISSION_BPS', 1500),
];
