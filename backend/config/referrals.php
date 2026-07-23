<?php

declare(strict_types=1);

return [
    // The reward booked to the referrer's promo_liability when a referral qualifies (minor units).
    'reward_minor' => (int) env('REFERRAL_REWARD_MINOR', 1000),
];
