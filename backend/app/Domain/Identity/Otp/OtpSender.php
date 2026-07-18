<?php

declare(strict_types=1);

namespace App\Domain\Identity\Otp;

/**
 * Delivers an OTP code to a phone. The real implementation (WhatsApp/SMS) arrives with the comms
 * layer (doc 07); until then LogOtpSender writes it to the log. Abstracted so the delivery channel
 * is swappable and so tests can capture the code without reading the stored hash.
 */
interface OtpSender
{
    public function send(string $phoneE164, string $code, string $purpose): void;
}
