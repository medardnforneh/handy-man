<?php

declare(strict_types=1);

namespace App\Domain\Notifications;

use Illuminate\Support\Facades\Log;

/**
 * Development SMS delivery — writes the message to the log. NEVER use in production; a real
 * aggregator adapter replaces it there.
 */
final class LogSmsSender implements SmsSender
{
    public function name(): string
    {
        return 'log';
    }

    public function send(string $phoneE164, string $message): void
    {
        Log::info("SMS to {$phoneE164}: {$message}");
    }
}
