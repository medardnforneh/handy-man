<?php

declare(strict_types=1);

namespace App\Domain\Identity\Actions;

use App\Domain\Access\Actions\SetAccountStatus;
use App\Domain\Verification\VerificationStorage;
use App\Models\Address;
use App\Models\Device;
use App\Models\EmergencyContact;
use App\Models\Job;
use App\Models\JobPhoto;
use App\Models\Media;
use App\Models\Message;
use App\Models\OtpChallenge;
use App\Models\Party;
use App\Models\ProviderProfile;
use App\Models\RefreshToken;
use App\Models\Review;
use App\Models\User;
use App\Models\VerificationDocument;
use App\Support\Outbox;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Crypto-shred erasure (build plan P1-10, doc 04). Resolves the erasure-vs-append-only-ledger
 * conflict: we do NOT delete the party (its id anchors ledger FKs in Phase 3). Instead we
 *   1. delete / null the PII-bearing rows and plaintext identifiers,
 *   2. destroy the content the person made — media bytes, job photos, message bodies, review prose,
 *   3. destroy the party's data key so any key-encrypted PII becomes unrecoverable,
 *   4. tombstone the party (`erased_at`) and close the account through the status machine.
 * The party row and `party_id` survive; the human becomes unidentifiable.
 *
 * Step 2 was missing entirely until 2026-09-30, and step 3 did nothing until the same day. Between
 * them they were the whole claim: the key encrypted nothing (see {@see VerificationStorage}), and
 * "unidentifiable" was asserted while the person's voice notes, photographs, messages and reviews
 * all stayed exactly where they were. What is erased and what is deliberately kept is now listed
 * at each step rather than implied, because under Law 2024/017 the distinction is the whole answer.
 *
 * NOT erased, on purpose: ledger entries and the engagement record (financial history, anchored by
 * the surviving party id), the aggregate provider history, `reviews.rating` (a number about someone
 * else's work), and the verification rows' sha256 (which recognises the same paper re-uploaded).
 * Message rows survive with empty bodies so the other party's thread and any dispute evidence stay
 * whole — that history is not this person's alone to delete.
 */
final class ErasePartyData
{
    public function __construct(
        private readonly Outbox $outbox,
        private readonly VerificationStorage $verification,
        private readonly SetAccountStatus $accounts,
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

            // 1e. The content this person made, which erasure used to leave behind entirely.
            //
            // "The human becomes unidentifiable" is what the docblock above says, and it was not
            // true: the voice notes are recordings of their voice, the report photos are their
            // premises, the messages are their words, and the reviews they wrote are their prose.
            // None of it was touched, and `config/retention.php` did not cover any of it either.
            //
            // The shape follows what each thing IS, not one rule for all of them:
            //
            //   media they own      → bytes destroyed, row kept with `purged_at`. A thread that
            //                         referenced a voice note should show that something was there
            //                         and is gone, not lose the reference silently.
            //   their job photos    → bytes and rows both. A `job_photos` row is nothing but a
            //                         path; without the file it says nothing worth keeping.
            //   messages they sent  → body nulled, row kept. Deleting the rows would tear holes in
            //                         the OTHER party's thread and in dispute evidence that is not
            //                         this person's to erase; a redacted bubble is honest.
            //   reviews they wrote  → free text nulled, `rating` kept. The number is an aggregate
            //                         about someone else's work, not personal data about the author.
            $this->purgeOwnedMedia($party->id);

            $jobIds = Job::query()->where('customer_party_id', $party->id)->pluck('id');
            $photos = JobPhoto::query()->whereIn('job_id', $jobIds)->get();
            DB::afterCommit(function () use ($photos): void {
                foreach ($photos as $photo) {
                    Storage::disk((string) config('filesystems.default'))->delete($photo->path);
                }
            });
            JobPhoto::query()->whereIn('job_id', $jobIds)->delete();

            Message::query()->where('sender_user_id', $user->getKey())->update(['body' => null]);

            Review::query()->where('author_party_id', $party->id)->update([
                'body' => null, 'private_note' => null,
            ]);

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
                // `status` is deliberately NOT here any more — the machine sets it below, for the
                // party and every user under it at once (rule #8).
                'app_authentication_secret' => null,
                'app_authentication_recovery_codes' => null,
            ])->save();

            // 2 + 3. Destroy the data key (crypto-shred) and tombstone the party — keep the row + id.
            //
            // The stored tombstone is locale-free (`Party::ERASED_NAME`). It cannot be translated
            // here — it is written once and read later by anyone, in either language — so what a
            // person sees comes from `Party::displayName()`, which keys on `erased_at` at render
            // time. This value is what an export, a ledger report or an admin table shows, and
            // being unmistakable there is exactly what it is for.
            $party->forceFill([
                'data_key' => null,
                'erased_at' => now(),
                'display_name' => Party::ERASED_NAME,
            ])->save();

            // The status half goes through the machine (CLAUDE.md rule #8) rather than onto the
            // row: `closed` is terminal, so it also revokes every session and marks the provider
            // profile suspended — which is what makes the erased account unable to sign back in
            // even before the tombstoned phone number would have stopped it.
            $this->accounts->close($party, reason: 'erasure');

            // The fact, for anything that keeps its own copy of who exists (analytics, exports).
            $this->outbox->publish('party.erased', ['party_id' => $party->id]);
        });
    }

    /**
     * Destroy the bytes of every media file this party owns, keeping the rows.
     *
     * The delete happens after commit — an S3/MinIO call inside the transaction would be an
     * external service holding a database lock (CLAUDE.md), and a rollback could not put the bytes
     * back anyway. `purged_at` is written inside, so the row and the intent commit together even if
     * the storage call later fails; the alternative leaves a row claiming bytes that are gone.
     */
    private function purgeOwnedMedia(string $partyId): void
    {
        $media = Media::query()->where('owner_party_id', $partyId)->whereNull('purged_at')->get();

        if ($media->isEmpty()) {
            return;
        }

        Media::query()->whereIn('id', $media->modelKeys())->update(['purged_at' => now()]);

        $disk = (string) config('filesystems.default');
        DB::afterCommit(function () use ($media, $disk): void {
            foreach ($media as $item) {
                Storage::disk($disk)->delete($item->storage_path);
            }
        });
    }
}
