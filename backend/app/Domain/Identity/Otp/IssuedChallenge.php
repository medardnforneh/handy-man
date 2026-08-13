<?php

declare(strict_types=1);

namespace App\Domain\Identity\Otp;

use App\Models\OtpChallenge;

/**
 * A live OTP challenge and the plaintext code that was sent for it.
 *
 * The code is deliberately part of the return value rather than hidden inside the action: the
 * action is what mints it, and the caller is the right place to decide who may see it. The API
 * controller's answer is "nobody, except a developer running this locally" — the stored column is
 * a hash and the code otherwise exists only for the moment it takes to hand to the sender.
 */
final readonly class IssuedChallenge
{
    public function __construct(
        public OtpChallenge $challenge,
        public string $code,
    ) {}
}
