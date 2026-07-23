<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Marketplace modes (build plan P8-03/P8-04)
    |--------------------------------------------------------------------------
    | Enable dispatch fan-out and bidding only when supply density supports them
    | (doc 01 §4). Bidding is OFF by default — behind a flag.
    */
    'dispatch_enabled' => (bool) env('MARKETPLACE_DISPATCH_ENABLED', false),
    'bidding_enabled' => (bool) env('MARKETPLACE_BIDDING_ENABLED', false),

    // How many providers a dispatch fan-out offers to at once (P8-03).
    'dispatch_fanout' => (int) env('MARKETPLACE_DISPATCH_FANOUT', 5),
];
