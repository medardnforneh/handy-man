<?php

declare(strict_types=1);

namespace App\Domain\Notifications;

/**
 * A normalised push notification (build plan P5-05). Provider-agnostic — the {@see PushSender}
 * adapter maps it onto FCM (or any future transport). `data` is the silent payload the app uses to
 * deep-link (e.g. the engagement/conversation to open); `title`/`body` are the visible alert.
 */
final class PushMessage
{
    /**
     * @param  array<string, string>  $data
     */
    public function __construct(
        public readonly string $title,
        public readonly string $body,
        public readonly array $data = [],
    ) {}
}
