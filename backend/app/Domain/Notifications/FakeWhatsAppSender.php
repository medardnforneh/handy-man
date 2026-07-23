<?php

declare(strict_types=1);

namespace App\Domain\Notifications;

/**
 * In-memory {@see WhatsAppSender} for tests and local dev. Records every send. Singleton, so a test
 * sees the same recorder the app used.
 */
final class FakeWhatsAppSender implements WhatsAppSender
{
    /** @var array<int, array{to: string, template: string, variables: array<int, string>, locale: string, deep_link: string|null}> */
    public array $sent = [];

    public function name(): string
    {
        return 'fake';
    }

    public function send(string $phoneE164, string $template, array $variables, string $locale, ?string $deepLink = null): void
    {
        $this->sent[] = ['to' => $phoneE164, 'template' => $template, 'variables' => $variables, 'locale' => $locale, 'deep_link' => $deepLink];
    }
}
