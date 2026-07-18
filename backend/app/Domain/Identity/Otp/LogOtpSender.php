<?php

declare(strict_types=1);

namespace App\Domain\Identity\Otp;

use Illuminate\Support\Facades\Log;

/**
 * Development OTP delivery — writes the code to the log. NEVER use in production; the comms layer
 * (doc 07) replaces this with WhatsApp/SMS delivery.
 */
final class LogOtpSender implements OtpSender
{
    public function send(string $phoneE164, string $code, string $purpose): void
    {
        Log::info("OTP for {$phoneE164} ({$purpose}): {$code}");
    }
}
