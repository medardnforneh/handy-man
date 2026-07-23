<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Double-blind review window
    |--------------------------------------------------------------------------
    | Both sides get this many days to submit. Nothing is visible until both have
    | submitted or the window closes — then both publish at once (doc 02/04).
    */
    'window_days' => (int) env('REVIEWS_WINDOW_DAYS', 14),

    /*
    |--------------------------------------------------------------------------
    | Bayesian shrinkage (display rating)
    |--------------------------------------------------------------------------
    | The displayed rating is shrunk toward the prior mean with this many pseudo-
    | reviews of weight, so a new pro's single 5★ can't outrank a veteran (P6-09).
    */
    'prior_mean' => (float) env('REVIEWS_PRIOR_MEAN', 4.0),
    'prior_weight' => (int) env('REVIEWS_PRIOR_WEIGHT', 10),
];
