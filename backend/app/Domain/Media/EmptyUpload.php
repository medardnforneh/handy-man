<?php

declare(strict_types=1);

namespace App\Domain\Media;

use App\Support\ProblemAware;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

/**
 * An upload arrived with no bytes — a recording that failed to capture, or a truncated transfer.
 *
 * The `media_bytes_check` constraint already refuses to store it, but as a 500. This turns it into
 * an honest 422 the client can act on ("that didn't record, try again") instead of an error page,
 * and it guards every media path, not just the one that surfaced it.
 */
final class EmptyUpload extends RuntimeException implements ProblemAware
{
    public function problemType(): string
    {
        return 'empty-upload';
    }

    public function problemTitle(): string
    {
        return 'The uploaded file was empty';
    }

    public function problemStatus(): int
    {
        return Response::HTTP_UNPROCESSABLE_ENTITY;
    }
}
