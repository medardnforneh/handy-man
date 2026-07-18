<?php

declare(strict_types=1);

namespace App\Domain\Identity;

use App\Support\ProblemAware;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

/**
 * OTP failures, rendered to RFC 7807 problem+json with a stable machine `type` (see
 * bootstrap/app.php). Deliberately vague messages — never reveal whether a phone exists or how
 * many attempts remain to an attacker.
 */
final class OtpException extends RuntimeException implements ProblemAware
{
    public function __construct(
        private readonly string $type,
        private readonly string $title,
        private readonly int $status,
        string $detail,
    ) {
        parent::__construct($detail);
    }

    public static function rateLimited(): self
    {
        return new self('otp-rate-limited', 'Too many OTP requests', Response::HTTP_TOO_MANY_REQUESTS,
            'Too many verification codes requested. Please wait and try again.');
    }

    public static function invalidOrExpired(): self
    {
        return new self('otp-invalid', 'Invalid or expired code', Response::HTTP_UNPROCESSABLE_ENTITY,
            'The code is incorrect or has expired. Request a new one.');
    }

    public static function locked(): self
    {
        return new self('otp-locked', 'Too many attempts', Response::HTTP_TOO_MANY_REQUESTS,
            'Too many incorrect attempts. Request a new code.');
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
