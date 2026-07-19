<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Active gateway
    |--------------------------------------------------------------------------
    | The concrete PaymentGateway the app resolves. 'fake' is the default (and what tests use);
    | switch to 'cinetpay' in staging/prod. The rest of the app never names a provider.
    */
    'gateway' => env('PAYMENTS_GATEWAY', 'fake'),

    'cinetpay' => [
        'apikey' => env('CINETPAY_APIKEY', ''),
        'site_id' => env('CINETPAY_SITE_ID', ''),
        'secret_key' => env('CINETPAY_SECRET_KEY', ''),
        'base_url' => env('CINETPAY_BASE_URL', 'https://api-checkout.cinetpay.com'),
        'notify_url' => env('CINETPAY_NOTIFY_URL', ''),
        'return_url' => env('CINETPAY_RETURN_URL', ''),
    ],
];
