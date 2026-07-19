<?php

declare(strict_types=1);

return [
    // How long a direct offer stays open before it expires (build plan P2-05).
    'ttl_hours' => (int) env('OFFER_TTL_HOURS', 48),
];
