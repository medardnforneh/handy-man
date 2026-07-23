<?php

declare(strict_types=1);

namespace App\Domain\Notifications;

/**
 * The WhatsApp Business API transport (build plan P7-05). WhatsApp is the workhorse channel for this
 * market. Business-initiated messages outside the 24h service window require pre-approved templates
 * (FR + EN) with deep links — an external human-queue dependency, so live sending waits on approval,
 * but the contract (named template + variables + locale + deep link) is fixed here. Fake/Log by
 * default; config-selected like the other rails.
 */
interface WhatsAppSender
{
    public function name(): string;

    /**
     * Send a templated message. `template` is the approved template name; `variables` fill its
     * placeholders; `deepLink` is the tap target back into the app. Best-effort — never throws for a
     * single unreachable number.
     *
     * @param  array<int, string>  $variables
     */
    public function send(string $phoneE164, string $template, array $variables, string $locale, ?string $deepLink = null): void;
}
