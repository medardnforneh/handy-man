<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Auto-approve window (build plan P3-11)
    |--------------------------------------------------------------------------
    | A submitted deliverable the customer neither accepts nor rejects within this
    | many hours is auto-approved, releasing the provider. A warning nudge fires
    | 24h before (at 48h).
    */
    'auto_approve_hours' => (int) env('DELIVERABLE_AUTO_APPROVE_HOURS', 72),
];
