<?php

declare(strict_types=1);

namespace App\Domain\Notifications;

/**
 * An in-memory {@see PushSender} for tests and local dev (build plan P5-05). It records every send so
 * a test can assert who was notified with what, and delivers to nobody. Bound as a singleton so a
 * test resolving it sees the same recorder the app used.
 */
final class FakePushSender implements PushSender
{
    /** @var array<int, array{tokens: array<int, string>, message: PushMessage}> */
    public array $sent = [];

    public function name(): string
    {
        return 'fake';
    }

    public function send(array $tokens, PushMessage $message): int
    {
        if ($tokens === []) {
            return 0;
        }

        $this->sent[] = ['tokens' => array_values($tokens), 'message' => $message];

        return count($tokens);
    }

    /**
     * @return array<int, string> every token ever sent to, flattened
     */
    public function allTokens(): array
    {
        return array_merge(...array_map(fn (array $s): array => $s['tokens'], $this->sent));
    }
}
