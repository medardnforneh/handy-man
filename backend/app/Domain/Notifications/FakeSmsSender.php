<?php

declare(strict_types=1);

namespace App\Domain\Notifications;

/**
 * An in-memory {@see SmsSender} for tests and local dev (build plan P6-04). Records every message so
 * a test can assert who was texted with what, and delivers to nobody. Singleton so a test resolving
 * it sees the same recorder the app used.
 */
final class FakeSmsSender implements SmsSender
{
    /** @var array<int, array{to: string, message: string}> */
    public array $sent = [];

    public function name(): string
    {
        return 'fake';
    }

    public function send(string $phoneE164, string $message): void
    {
        $this->sent[] = ['to' => $phoneE164, 'message' => $message];
    }

    /**
     * @return array<int, string> every number ever texted
     */
    public function recipients(): array
    {
        return array_map(fn (array $s): string => $s['to'], $this->sent);
    }
}
