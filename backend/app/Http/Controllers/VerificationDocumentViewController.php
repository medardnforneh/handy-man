<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Verification\VerificationStorage;
use App\Models\VerificationDocument;
use App\Support\ActivityLogger;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use League\Flysystem\UnableToReadFile;
use Symfony\Component\HttpFoundation\Response;

/**
 * Streams a verification document's plaintext through a signed, short-TTL URL (build plan P6-01,
 * doc 04). The `signed` middleware is the gate — an expired or tampered URL is rejected (403) before
 * this runs, so a leaked link is useless within a minute. The file is decrypted here and served
 * inline; it is never exposed at a public or guessable path.
 */
final class VerificationDocumentViewController extends Controller
{
    public function __invoke(Request $request, VerificationDocument $document, VerificationStorage $storage, ActivityLogger $log): Response
    {
        // The insider-threat control (doc 04): every view — not just edits — is logged, with the
        // viewer (when the browser carries an admin session) and their IP.
        $log->log(
            action: 'verification_document.viewed',
            subject: $document,
            actorUserId: Auth::id() !== null ? (string) Auth::id() : null,
            ip: $request->ip(),
        );

        // A row can outlive its file — a bucket restored from an older snapshot, a partial upload,
        // an erasure that purged the object and left the record. That is a missing document, not a
        // server fault, and it should say so instead of raising a 500 at a reviewer who is halfway
        // through a queue. `decryptString` throws the same way on absent bytes as on corrupt ones.
        try {
            $bytes = $storage->read($document);
        } catch (DecryptException|UnableToReadFile $e) {
            abort(Response::HTTP_NOT_FOUND, __('admin.verification.file_missing'));
        }

        $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes) ?: 'application/octet-stream';

        return response($bytes, 200, [
            'Content-Type' => $mime,
            'Content-Disposition' => 'inline',
            'Cache-Control' => 'no-store, private',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
