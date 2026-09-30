<?php

declare(strict_types=1);

namespace App\Domain\Verification;

use App\Support\ProblemAware;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

/**
 * The key that could decrypt this document no longer exists.
 *
 * For a `party_key` document whose party has been erased, this is the crypto-shred working: the
 * bytes are purged as well, but if a copy survived anywhere it is now noise. 410 Gone rather than
 * 500 or 404 — the document existed, it was destroyed deliberately, and a reviewer looking at the
 * audit trail should be told which of those happened.
 */
final class DocumentUnreadable extends RuntimeException implements ProblemAware
{
    public function __construct()
    {
        parent::__construct('This document can no longer be decrypted — its key was destroyed.');
    }

    public function problemType(): string
    {
        return 'document-unreadable';
    }

    public function problemTitle(): string
    {
        return 'Document destroyed';
    }

    public function problemStatus(): int
    {
        return Response::HTTP_GONE;
    }
}
