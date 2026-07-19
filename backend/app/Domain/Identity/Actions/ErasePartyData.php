<?php

declare(strict_types=1);

namespace App\Domain\Identity\Actions;

use App\Models\Address;
use App\Models\Device;
use App\Models\OtpChallenge;
use App\Models\ProviderProfile;
use App\Models\RefreshToken;
use App\Models\User;
use App\Support\Outbox;
use Illuminate\Support\Facades\DB;

/**
 * Crypto-shred erasure (build plan P1-10, doc 04). Resolves the erasure-vs-append-only-ledger
 * conflict: we do NOT delete the party (its id anchors ledger FKs in Phase 3). Instead we
 *   1. delete / null the PII-bearing rows and plaintext identifiers,
 *   2. destroy the party's data key so any key-encrypted PII becomes unrecoverable,
 *   3. tombstone the party (`erased_at`).
 * The party row and `party_id` survive; the human becomes unidentifiable.
 */
final class ErasePartyData
{
    public function __construct(
        private readonly Outbox $outbox,
    ) {}

    public function handle(User $user): void
    {
        DB::transaction(function () use ($user): void {
            $party = $user->party;

            // 1a. Drop PII-bearing rows attached to the user/party.
            Address::query()->where('party_id', $party->id)->delete();
            Device::query()->where('user_id', $user->getKey())->delete();
            RefreshToken::query()->where('user_id', $user->getKey())->delete();
            OtpChallenge::query()->where('phone_e164', $user->phone_e164)->delete();
            $user->tokens()->delete(); // Sanctum access tokens

            // 1b. Null free-text PII on the provider profile, but keep the row — its aggregate
            // history (jobs_completed, ratings) is not personal data and may anchor FKs.
            ProviderProfile::query()->where('party_id', $party->id)->update([
                'headline' => null, 'bio' => null, 'bio_language' => null,
            ]);

            // 1c. Null the plaintext identifiers on the user. phone_e164 is NOT NULL + unique, so it
            // gets a non-identifying tombstone rather than null.
            $user->forceFill([
                'email' => null,
                'password_hash' => null,
                'phone_e164' => 'erased-'.$party->id,
                'phone_verified_at' => null,
                'email_verified_at' => null,
                'status' => 'closed',
                'app_authentication_secret' => null,
                'app_authentication_recovery_codes' => null,
            ])->save();

            // 2 + 3. Destroy the data key (crypto-shred) and tombstone the party — keep the row + id.
            $party->forceFill([
                'data_key' => null,
                'erased_at' => now(),
                'display_name' => 'Utilisateur supprimé',
                'status' => 'closed',
            ])->save();

            // Downstream cleanup (e.g. purge the verification-document bucket in P6) runs off this.
            $this->outbox->publish('party.erased', ['party_id' => $party->id]);
        });
    }
}
