<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Active push sender
    |--------------------------------------------------------------------------
    | The concrete PushSender the app resolves. 'fake' is the default (and what tests use); switch to
    | 'fcm' in staging/prod. The rest of the app never names a provider — it depends on the interface.
    */
    'push' => env('PUSH_SENDER', 'fake'),

    'fcm' => [
        // FCM HTTP v1: https://fcm.googleapis.com/v1/projects/{project_id}/messages:send
        'project_id' => env('FCM_PROJECT_ID', ''),
        // A pre-obtained OAuth2 access token (or service-account exchange, wired at deploy). Kept out
        // of the domain so the sender stays a thin HTTP adapter — live delivery pends real creds.
        'access_token' => env('FCM_ACCESS_TOKEN', ''),
        'base_url' => env('FCM_BASE_URL', 'https://fcm.googleapis.com'),
    ],
];
