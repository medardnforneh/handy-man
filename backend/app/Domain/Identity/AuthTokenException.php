<?php

declare(strict_types=1);

namespace App\Domain\Identity;

use App\Support\ProblemAware;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Refresh-token failures (build plan P1-03). Rendered to problem+json. Reuse detection is the
 * important one: presenting an already-rotated token means it leaked, so the whole family is
 * revoked and the client must re-authenticate.
 */
final class AuthTokenException extends RuntimeException implements ProblemAware
{
    public function __construct(
        private readonly string $type,
        private readonly string $title,
        private readonly int $status,
        string $detail,
    ) {
        parent::__construct($detail);
    }

    public static function invalidRefresh(): self
    {
        return new self('refresh-token-invalid', 'Invalid refresh token', Response::HTTP_UNAUTHORIZED,
            'The refresh token is invalid or has expired. Please sign in again.');
    }

    public static function reuseDetected(): self
    {
        return new self('refresh-token-reused', 'Session revoked', Response::HTTP_UNAUTHORIZED,
            'This session was revoked for security. Please sign in again.');
    }

    public function problemType(): string
    {
        return $this->type;
    }

    public function problemTitle(): string
    {
        return $this->title;
    }

    public function problemStatus(): int
    {
        return $this->status;
    }
}
