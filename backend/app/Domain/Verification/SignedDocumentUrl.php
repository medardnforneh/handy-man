<?php

declare(strict_types=1);

namespace App\Domain\Verification;

use App\Models\VerificationDocument;
use Illuminate\Support\Facades\URL;

/**
 * Mints a signed, short-TTL URL to view a verification document (build plan P6-01, doc 04). Never a
 * public or permanent link — the signature is the access grant and it expires in {@see TTL_SECONDS}.
 * Access flows through the app route (not a direct bucket URL) so that every view can be logged
 * (P6-02) — a presigned bucket link would be invisible to the audit trail.
 */
final class SignedDocumentUrl
{
    public const TTL_SECONDS = 60;

    public function for(VerificationDocument $document, ?int $ttlSeconds = null): string
    {
        return URL::temporarySignedRoute(
            'verification-documents.view',
            now()->addSeconds($ttlSeconds ?? self::TTL_SECONDS),
            ['document' => $document->id],
        );
    }
}
