<?php

declare(strict_types=1);

namespace App\Domain\Verification;

use App\Support\ProblemAware;
use App\Support\ProvidesProblemExtras;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

/**
 * A verification document above what the encryption path will hold in memory.
 *
 * The request rules already cap an upload at 10M, so in normal operation this never fires — it is
 * the backstop for the day someone raises that cap and does not notice that `VerificationStorage`
 * keeps the plaintext and the ciphertext resident at the same time. A sentence beats an OOM.
 */
final class DocumentTooLarge extends RuntimeException implements ProblemAware, ProvidesProblemExtras
{
    public function __construct(private readonly int $maxBytes)
    {
        parent::__construct('That document is too large to store.');
    }

    public function problemType(): string
    {
        return 'document-too-large';
    }

    public function problemTitle(): string
    {
        return 'Document too large';
    }

    public function problemStatus(): int
    {
        return Response::HTTP_UNPROCESSABLE_ENTITY;
    }

    /**
     * @return array<string, mixed>
     */
    public function problemExtras(): array
    {
        return ['max_bytes' => $this->maxBytes];
    }
}
