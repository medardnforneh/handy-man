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

    /*
    |--------------------------------------------------------------------------
    | Active SMS sender
    |--------------------------------------------------------------------------
    | OTPs (when `otp.sender` is 'sms'), emergency-contact texts (panic alerts) and the last rung
    | of the follow-up ladder ride this. 'fake' in tests, 'log' in local dev, 'twilio' in prod.
    | The app depends only on the interface.
    */
    'sms' => env('SMS_SENDER', 'fake'),

    'twilio' => [
        'account_sid' => env('TWILIO_ACCOUNT_SID', ''),
        'auth_token' => env('TWILIO_AUTH_TOKEN', ''),
        // A purchased number (E.164), an alphanumeric sender id ("HandyMan" — supported for
        // Cameroon, no reply path), or a Messaging Service SID (MG…).
        'from' => env('TWILIO_FROM', ''),
        'base_url' => env('TWILIO_BASE_URL', 'https://api.twilio.com'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Active WhatsApp sender
    |--------------------------------------------------------------------------
    | The workhorse follow-up channel (doc 07). 'fake' in tests, 'log' in dev, 'meta' (the
    | Cloud API) in prod once the two generic templates below are approved.
    */
    'whatsapp' => env('WHATSAPP_SENDER', 'fake'),

    /*
    |--------------------------------------------------------------------------
    | Where a follow-up's link lands
    |--------------------------------------------------------------------------
    | Every outbound nudge (WhatsApp button, SMS text) links to `{base}/{follow-up id}`, and the APP
    | answers that route (mobile: `follow-up/:id` → records the tap, opens what it is about). The
    | app is on its own host in production (app.handyman.cm), so this is not APP_URL there — and
    | it is the URL approved on the WhatsApp template's button, so changing it means re-approval.
    */
    'follow_up_link_base' => env('FOLLOW_UP_LINK_BASE', rtrim((string) env('APP_URL', ''), '/').'/follow-up'),

    'whatsapp_meta' => [
        // A System User token with whatsapp_business_messaging, from the Meta Business portfolio.
        'access_token' => env('WHATSAPP_ACCESS_TOKEN', ''),
        // The sender: the business phone number's id (not the number itself).
        'phone_number_id' => env('WHATSAPP_PHONE_NUMBER_ID', ''),
        'base_url' => env('WHATSAPP_BASE_URL', 'https://graph.facebook.com'),
        'api_version' => env('WHATSAPP_API_VERSION', 'v21.0'),
        // ONE generic utility template, submitted once per language (docs/07): {{1}} title,
        // {{2}} body, a URL button whose dynamic suffix is the follow-up id. Every follow-up kind
        // rides it unless it is given a template of its own in `templates`.
        'template' => env('WHATSAPP_TEMPLATE', 'handyman_follow_up'),
        'templates' => [
            // 'review_request' => 'handyman_review_request',
        ],
        // Our locale → the language code the template was approved under.
        'languages' => ['fr' => 'fr', 'en' => 'en'],
    ],

    'fcm' => [
        // FCM HTTP v1: https://fcm.googleapis.com/v1/projects/{project_id}/messages:send
        'project_id' => env('FCM_PROJECT_ID', ''),
        // A pre-obtained OAuth2 access token (or service-account exchange, wired at deploy). Kept out
        // of the domain so the sender stays a thin HTTP adapter — live delivery pends real creds.
        'access_token' => env('FCM_ACCESS_TOKEN', ''),
        'base_url' => env('FCM_BASE_URL', 'https://fcm.googleapis.com'),
    ],
];
