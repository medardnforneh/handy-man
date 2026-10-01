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

    /*
    |--------------------------------------------------------------------------
    | Gateway HTTP budget
    |--------------------------------------------------------------------------
    | Every outbound call to a gateway is bounded. It had no timeout at all, which on these
    | networks is not a theoretical problem: a hung aggregator became a hung PHP-FPM worker, and
    | a payout request that had committed its reservation sat waiting on a socket. Keep these
    | SHORT — a gateway that has not answered in twenty seconds is not going to, and both the
    | webhook and the reconciliation poller will resolve the payment without this request.
    */
    'http' => [
        'connect_timeout' => (int) env('PAYMENTS_HTTP_CONNECT_TIMEOUT', 5),
        'timeout' => (int) env('PAYMENTS_HTTP_TIMEOUT', 20),
    ],

    'cinetpay' => [
        'apikey' => env('CINETPAY_APIKEY', ''),
        'site_id' => env('CINETPAY_SITE_ID', ''),
        'secret_key' => env('CINETPAY_SECRET_KEY', ''),
        'base_url' => env('CINETPAY_BASE_URL', 'https://api-checkout.cinetpay.com'),
        'notify_url' => env('CINETPAY_NOTIFY_URL', ''),
        'return_url' => env('CINETPAY_RETURN_URL', ''),
    ],
];
