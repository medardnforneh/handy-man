<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Check-in overdue watchdog (P6-06)
    |--------------------------------------------------------------------------
    | How long after a booking's scheduled start a worker may go without checking
    | in before the watchdog raises a `check_in_overdue` safety alert.
    */
    'checkin_grace_minutes' => (int) env('SAFETY_CHECKIN_GRACE_MINUTES', 15),

    /*
    |--------------------------------------------------------------------------
    | Share-my-job link (P6-05)
    |--------------------------------------------------------------------------
    | Default lifetime of a share link. Long enough to cover a job, short enough
    | that a leaked link goes stale.
    */
    'share_ttl_hours' => (int) env('SAFETY_SHARE_TTL_HOURS', 8),
];
