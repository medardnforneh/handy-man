<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Verification\VerificationStorage;
use App\Models\VerificationDocument;
use Symfony\Component\HttpFoundation\Response;

/**
 * Streams a verification document's plaintext through a signed, short-TTL URL (build plan P6-01,
 * doc 04). The `signed` middleware is the gate — an expired or tampered URL is rejected (403) before
 * this runs, so a leaked link is useless within a minute. The file is decrypted here and served
 * inline; it is never exposed at a public or guessable path.
 */
final class VerificationDocumentViewController extends Controller
{
    public function __invoke(VerificationDocument $document, VerificationStorage $storage): Response
    {
        $bytes = $storage->read($document);
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes) ?: 'application/octet-stream';

        return response($bytes, 200, [
            'Content-Type' => $mime,
            'Content-Disposition' => 'inline',
            'Cache-Control' => 'no-store, private',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
