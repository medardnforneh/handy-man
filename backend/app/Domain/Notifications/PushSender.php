<?php

declare(strict_types=1);

namespace App\Domain\Notifications;

/**
 * The push transport abstraction (build plan P5-05). The app depends on this interface; the concrete
 * sender (FCM in prod, Fake in tests/local) is selected by `config('notifications.push')`. Nothing
 * else in the app names a provider.
 */
interface PushSender
{
    public function name(): string;

    /**
     * Deliver a message to the given device push tokens. Returns the number of tokens accepted for
     * delivery. Implementations must tolerate an empty token list (a no-op) and never throw for an
     * individual unreachable token — a dead token is not a failure of the batch.
     *
     * @param  array<int, string>  $tokens
     */
    public function send(array $tokens, PushMessage $message): int;
}
