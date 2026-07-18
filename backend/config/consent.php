<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Consent purposes (doc 04 §4, Law No. 2024/017)
    |--------------------------------------------------------------------------
    |
    | Consent is the lawful basis — "legitimate interest" is NOT available.
    | Each purpose is separately grantable and separately revocable. Consent is
    | explicit, informed, specific, voluntary, versioned and revocable.
    |
    */

    'purposes' => ['terms', 'privacy', 'location_tracking', 'id_verification', 'marketing'],

    // The current policy version presented to users. Bumping this means prior consent to an older
    // version no longer counts as consent to the new one.
    'policy_version' => env('CONSENT_POLICY_VERSION', '2026-07-01'),

];
