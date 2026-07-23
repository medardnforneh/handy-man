<?php

declare(strict_types=1);

namespace App\Domain\Notifications;

use Illuminate\Support\Facades\Log;

/**
 * Development WhatsApp delivery — writes the template send to the log. NEVER use in production; the
 * WhatsApp Business API adapter replaces it once templates are approved.
 */
final class LogWhatsAppSender implements WhatsAppSender
{
    public function name(): string
    {
        return 'log';
    }

    public function send(string $phoneE164, string $template, array $variables, string $locale, ?string $deepLink = null): void
    {
        Log::info("WhatsApp [{$template}/{$locale}] to {$phoneE164}", ['variables' => $variables, 'deep_link' => $deepLink]);
    }
}
