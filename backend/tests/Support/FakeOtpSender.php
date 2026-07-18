<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Domain\Identity\Otp\OtpSender;

/**
 * Test double that captures OTP codes in memory so tests can complete the verify flow without
 * reading the stored hash (and without any real SMS delivery).
 */
final class FakeOtpSender implements OtpSender
{
    /** @var array<string, string> phone => last code sent */
    public array $sent = [];

    public function send(string $phoneE164, string $code, string $purpose): void
    {
        $this->sent[$phoneE164] = $code;
    }

    public function codeFor(string $phoneE164): ?string
    {
        return $this->sent[$phoneE164] ?? null;
    }
}
