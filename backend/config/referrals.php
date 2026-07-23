<?php

declare(strict_types=1);

return [
    // The reward booked to the referrer's promo_liability when a referral qualifies (minor units).
    'reward_minor' => (int) env('REFERRAL_REWARD_MINOR', 1000),

    // Fraud control (P8-02): referrals beyond this many per referrer per rolling week are flagged for
    // a human review instead of auto-qualifying.
    'weekly_velocity_limit' => (int) env('REFERRAL_WEEKLY_LIMIT', 5),
];
