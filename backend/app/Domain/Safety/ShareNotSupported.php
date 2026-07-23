<?php

declare(strict_types=1);

namespace App\Domain\Safety;

use App\Support\ProblemAware;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Share-my-job was requested for an engagement with no physical presence (build plan P6-05). It is a
 * presence-safety feature — remote work exposes no share link.
 */
final class ShareNotSupported extends RuntimeException implements ProblemAware
{
    public function problemType(): string
    {
        return 'share-not-supported';
    }

    public function problemTitle(): string
    {
        return 'This engagement does not support share-my-job';
    }

    public function problemStatus(): int
    {
        return Response::HTTP_UNPROCESSABLE_ENTITY;
    }
}
