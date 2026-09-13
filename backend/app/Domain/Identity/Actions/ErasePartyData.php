<?php

declare(strict_types=1);

namespace App\Domain\Identity\Actions;

use App\Domain\Verification\VerificationStorage;
use App\Models\Address;
use App\Models\Device;
use App\Models\EmergencyContact;
use App\Models\OtpChallenge;
use App\Models\ProviderProfile;
use App\Models\RefreshToken;
use App\Models\User;
use App\Models\VerificationDocument;
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
        private readonly VerificationStorage $verification,
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
            // Emergency contacts are OTHER people's names and numbers, held only for this person's
            // safety; with the person gone there is no basis to keep them (doc 04).
            EmergencyContact::query()->where('user_id', $user->getKey())->delete();

            // 1d. The identity papers. P1-10 announced `party.erased` "for downstream cleanup in
            // P6" and P6 never subscribed — an erased person's ID scans stayed in the bucket,
            // decryptable, for ever. The bytes go now; the rows stay for the audit trail (who
            // reviewed what, and the sha256 that recognises the same paper re-uploaded).
            $documents = VerificationDocument::query()->where('party_id', $party->id)->whereNull('purged_at')->get();
            DB::afterCommit(function () use ($documents): void {
                foreach ($documents as $document) {
                    $this->verification->purge($document);
                }
            });

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

            // The fact, for anything that keeps its own copy of who exists (analytics, exports).
            $this->outbox->publish('party.erased', ['party_id' => $party->id]);
        });
    }
}
