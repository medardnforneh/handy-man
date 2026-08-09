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

    /*
    |--------------------------------------------------------------------------
    | Unconverted-quote nudges (P2.5-06)
    |--------------------------------------------------------------------------
    | The highest-ROI message on the list — the lead is already paid for.
    */
    'quote_pending_hours' => (int) env('FOLLOWUPS_QUOTE_PENDING_HOURS', 24),
    'quote_expiring_lead_hours' => (int) env('FOLLOWUPS_QUOTE_EXPIRING_LEAD_HOURS', 24),

    /*
    |--------------------------------------------------------------------------
    | The rest of the doc-07 catalogue
    |--------------------------------------------------------------------------
    */
    // A job open with nobody offering — the customer's most likely moment to give up on us.
    'job_unquoted_hours' => (int) env('FOLLOWUPS_JOB_UNQUOTED_HOURS', 6),

    // Work submitted and waiting on the customer, well before the auto-approve deadline warning.
    'awaiting_approval_hours' => (int) env('FOLLOWUPS_AWAITING_APPROVAL_HOURS', 24),

    // Don't tell a provider to withdraw a trivial balance: the message costs us and the transfer
    // costs them. 50 000 XAF is a withdrawal worth making a trip for.
    'payout_ready_threshold_minor' => (int) env('FOLLOWUPS_PAYOUT_READY_THRESHOLD_MINOR', 50_000),
];
