<?php

declare(strict_types=1);

namespace App\Domain\Notifications;

/**
 * The SMS transport abstraction (build plan P6-04). Emergency-contact texts on a panic alert ride
 * this. The app depends on the interface; the concrete sender is selected by
 * `config('notifications.sms')` — Fake in tests, Log in dev, a real aggregator in prod.
 */
interface SmsSender
{
    public function name(): string;

    /**
     * Deliver a plain-text message to an E.164 phone number. Best-effort — implementations should not
     * throw for one unreachable number (a panic fan-out must reach the others).
     */
    public function send(string $phoneE164, string $message): void;
}
