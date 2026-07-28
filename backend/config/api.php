<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Current API version
    |--------------------------------------------------------------------------
    |
    | The API is additive-only and versioned in the URL (/api/v1). This is the
    | version the server currently speaks. Old app builds keep talking to the
    | same /v1 for months — never remove a field, never tighten a rule.
    |
    */

    'version' => 'v1',

    /*
    |--------------------------------------------------------------------------
    | Minimum supported app build (force-update kill switch)
    |--------------------------------------------------------------------------
    |
    | Every request from the mobile app sends `X-App-Version: MAJOR.MINOR.PATCH`.
    | If a build is older than `min_app_version`, the API responds 426 Upgrade
    | Required and the app must send the user to the store. Set to null to
    | disable the gate (e.g. for the web/Blade surface which sends no build).
    |
    | Bump this only to shut off builds with a broken/unsafe contract.
    |
    */

    'min_app_version' => env('API_MIN_APP_VERSION', '1.0.0'),

    /*
    |--------------------------------------------------------------------------
    | App-version header name
    |--------------------------------------------------------------------------
    */

    'app_version_header' => 'X-App-Version',
    'device_id_header' => 'X-Device-Id',

    /*
    |--------------------------------------------------------------------------
    | Problem+json base type URI
    |--------------------------------------------------------------------------
    |
    | RFC 7807 `type` values are namespaced under this base. Keep the machine
    | slug stable — support and clients switch on it.
    |
    */

    'problem_type_base' => env('API_PROBLEM_TYPE_BASE', 'https://errors.handyman.cm'),

    /*
    |--------------------------------------------------------------------------
    | Idempotency (CLAUDE.md rule #3)
    |--------------------------------------------------------------------------
    |
    | Mutating requests carry an `Idempotency-Key`; a replay returns the stored
    | response without re-executing. `ttl_hours` bounds how long a key is
    | remembered. `require_on_mutations` refuses mutating API calls that omit
    | the header (mobile MUST send it). `exempt_paths` are api-relative globs
    | that skip the requirement (none by default).
    |
    */

    'idempotency' => [
        'header' => 'Idempotency-Key',
        'ttl_hours' => (int) env('API_IDEMPOTENCY_TTL_HOURS', 24),
        'require_on_mutations' => (bool) env('API_IDEMPOTENCY_REQUIRED', true),
        'exempt_paths' => [
            'api/v1/webhooks/*', // gateway webhooks are server-to-server; they carry no Idempotency-Key
            // Channel authorization is a handshake, not a mutation — Echo issues it and has no
            // Idempotency-Key to give; replaying one would be meaningless anyway.
            'api/v1/broadcasting/auth',
        ],
    ],

];
