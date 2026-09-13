<?php

declare(strict_types=1);

namespace App\Domain\Verification;

use App\Models\VerificationDocument;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Reads and writes verification documents to their dedicated bucket, encrypted at rest (build plan
 * P6-01, doc 04). Encryption is applied by the app on top of whatever the bucket provides, so the
 * bytes on disk are never the plaintext ID even if the storage layer is misconfigured. Access is
 * always mediated — there is no public URL and no path a caller can guess to the plaintext.
 */
final class VerificationStorage
{
    public function disk(): string
    {
        return 'verification';
    }

    /**
     * Encrypt and store the upload. Returns [storagePath, sha256OfPlaintext].
     *
     * @return array{0: string, 1: string}
     */
    public function store(UploadedFile $file): array
    {
        $plaintext = (string) file_get_contents($file->getRealPath());
        $sha256 = hash('sha256', $plaintext);
        $path = 'documents/'.Str::uuid()->toString().'.enc';

        Storage::disk($this->disk())->put($path, Crypt::encryptString($plaintext));

        return [$path, $sha256];
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
     */
    public function read(VerificationDocument $document): string
    {
        $ciphertext = (string) Storage::disk($this->disk())->get($document->storage_path);

        return Crypt::decryptString($ciphertext);
    }
}
