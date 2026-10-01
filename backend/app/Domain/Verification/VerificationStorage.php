<?php

declare(strict_types=1);

namespace App\Domain\Verification;

use App\Models\Party;
use App\Models\VerificationDocument;
use Illuminate\Contracts\Encryption\Encrypter as EncrypterContract;
use Illuminate\Encryption\Encrypter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Reads and writes verification documents to their dedicated bucket, encrypted at rest (build plan
 * P6-01, doc 04). Encryption is applied by the app on top of whatever the bucket provides, so the
 * bytes on disk are never the plaintext ID even if the storage layer is misconfigured. Access is
 * always mediated — there is no public URL and no path a caller can guess to the plaintext.
 *
 * ENCRYPTED WITH THE OWNING PARTY'S OWN KEY (`parties.data_key`), which is what makes erasure's
 * crypto-shred real. It used to be `Crypt`, i.e. the application key — shared by every party, and
 * not something erasure can destroy without locking the whole platform out of its own data. So
 * destroying a party's key protected nothing, and the only reason an erased person's papers
 * actually went away was the explicit byte-delete added later.
 *
 * Documents written before that change stay readable under `app_key`; the scheme is recorded per
 * row (`encryption_scheme`) rather than guessed, because a document that cannot be decrypted is
 * indistinguishable from one that was never valid. New documents are always `party_key`.
 */
final class VerificationStorage
{
    public const SCHEME_APP_KEY = 'app_key';

    public const SCHEME_PARTY_KEY = 'party_key';

    /**
     * The largest document this will encrypt, in bytes.
     *
     * Matched to the 10M the request rules already allow (`max:10240`), so it changes nothing
     * today — it exists so that raising the validation rule without also thinking about
     * `memory_limit` fails with a sentence instead of an out-of-memory error.
     */
    public const MAX_BYTES = 10 * 1024 * 1024;

    public function disk(): string
    {
        return 'verification';
    }

    /**
     * Encrypt and store the upload under the party's own key.
     *
     * Returns [storagePath, sha256OfPlaintext, scheme]. The sha256 is of the PLAINTEXT and is what
     * recognises the same paper re-uploaded, so it survives the purge while the bytes do not.
     *
     * @return array{0: string, 1: string, 2: string}
     */
    public function store(UploadedFile $file, Party $party): array
    {
        // Encryption here is whole-file in memory: the plaintext and its ciphertext are both
        // resident at once, and `read()` decrypts the lot before streaming. That is comfortable
        // against the 10M the request rules allow and `memory_limit=256M` — and it stops being
        // comfortable the moment someone raises the former without thinking about the latter.
        // Refuse loudly at a bound of our own rather than discovering it as an OOM on the one
        // upload path where the person has no way to retry smaller.
        if ($file->getSize() > self::MAX_BYTES) {
            throw new DocumentTooLarge(self::MAX_BYTES);
        }

        $plaintext = (string) file_get_contents($file->getRealPath());
        $sha256 = hash('sha256', $plaintext);
        $path = 'documents/'.Str::uuid()->toString().'.enc';

        Storage::disk($this->disk())->put($path, $this->encrypterFor($party)->encryptString($plaintext));

        return [$path, $sha256, self::SCHEME_PARTY_KEY];
    }

    /**
     * Destroy the bytes and say so on the row (doc 04 retention; erasure). The record survives —
     * who reviewed what and when, and the plaintext's sha256 so the same paper re-uploaded is still
     * recognised — but nothing decryptable remains, on this disk or anywhere the app can reach.
     * Idempotent: a document purged twice is purged.
     */
    public function purge(VerificationDocument $document): void
    {
        if ($document->purged_at !== null) {
            return;
        }

        Storage::disk($this->disk())->delete($document->storage_path);
        $document->forceFill(['purged_at' => now()])->save();
    }

    /**
     * Decrypt and return the document's plaintext bytes for streaming through a signed URL.
     *
     * @throws DocumentUnreadable when the key that could read it is gone — which, for a
     *                            `party_key` document belonging to an erased party, is the design
     *                            working rather than a fault.
     */
    public function read(VerificationDocument $document): string
    {
        $ciphertext = (string) Storage::disk($this->disk())->get($document->storage_path);

        if ($document->encryption_scheme === self::SCHEME_APP_KEY || $document->encryption_scheme === null) {
            return Crypt::decryptString($ciphertext);
        }

        $party = Party::query()->find($document->party_id);

        if ($party === null || $party->data_key === null) {
            throw new DocumentUnreadable;
        }

        return $this->encrypterFor($party)->decryptString($ciphertext);
    }

    /**
     * An encrypter bound to one party's key.
     *
     * `data_key` is 32 base64'd random bytes (see Party::booted), which is exactly the key length
     * every cipher the app is configured with wants — so it is used directly rather than derived,
     * and there is no salt to store or lose.
     */
    private function encrypterFor(Party $party): EncrypterContract
    {
        $key = base64_decode((string) $party->data_key, true);

        if ($key === false || $key === '') {
            throw new DocumentUnreadable;
        }

        return new Encrypter($key, (string) config('app.cipher'));
    }
}
