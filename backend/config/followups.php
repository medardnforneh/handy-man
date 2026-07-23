<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Per-user, per-channel budget (doc 07)
    |--------------------------------------------------------------------------
    | Rolling-window caps on non-transactional sends. Over budget → suppressed,
    | not sent. in_app is unlimited (absent here). SMS costs money — keep it tight.
    | Transactional kinds (check-in overdue, auto-approve warning, payout ready)
    | bypass this entirely.
    */
    'budget' => [
        'push' => ['day' => 4],
        'sms' => ['day' => 2, 'week' => 3],
        'whatsapp' => ['day' => 3],
        'email' => ['day' => 2],
    ],
];
