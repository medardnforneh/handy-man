<?php

declare(strict_types=1);

namespace App\Domain\Identity\Actions;

use App\Models\Consent;
use App\Models\User;

/**
 * Records a consent decision (build plan P1-05). Append-only: each grant or revoke is a new row, so
 * the audit trail is complete. Captures the policy version and the locale the policy was PRESENTED
 * in — consent shown in a language the user doesn't operate in is not "informed" (doc 04).
 */
final class RecordConsent
{
    public function handle(User $user, string $purpose, bool $granted, string $presentedLocale): Consent
    {
        return Consent::query()->create([
            'user_id' => $user->getKey(),
            'purpose' => $purpose,
            'granted' => $granted,
            'policy_version' => (string) config('consent.policy_version'),
            'presented_locale' => $presentedLocale,
            'created_at' => now(),
        ]);
    }
}
