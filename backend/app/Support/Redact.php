<?php

declare(strict_types=1);

namespace App\Support;

/**
 * What a log line may say about a person (doc 04). Logs are read by whoever operates the box,
 * shipped wherever the box ships them, and kept for as long as the retention says — none of which
 * a phone number needs. Enough survives to tell one line from another and to match a support
 * ticket ("the number ending 111"), not enough to call anyone.
 */
final class Redact
{
    /** `+237677000111` → `+2376…111`. */
    public static function phone(string $phoneE164): string
    {
        $digits = preg_replace('/\D/', '', $phoneE164) ?? '';
        if (strlen($digits) < 8) {
            return '…';
        }

        return '+'.substr($digits, 0, 4).'…'.substr($digits, -3);
    }
}
