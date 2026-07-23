<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Provider metrics (P6-12 / P6-13)
    |--------------------------------------------------------------------------
    */
    // Rolling window for displayed performance metrics.
    'window_days' => (int) env('METRICS_WINDOW_DAYS', 90),

    // Sample-size floor: a rate computed from fewer than this many data points is not displayed
    // ("100% on-time (1 job)" is misleading).
    'sample_floor' => (int) env('METRICS_SAMPLE_FLOOR', 5),

    // Leakage proxy (P6-13): a provider with many completions but few repeat customers is *flagged*
    // for a human look — never auto-accused.
    'leakage_min_completed' => (int) env('METRICS_LEAKAGE_MIN_COMPLETED', 8),
    'leakage_repeat_threshold' => (float) env('METRICS_LEAKAGE_REPEAT_THRESHOLD', 0.15),
];
