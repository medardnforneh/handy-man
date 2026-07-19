<?php

declare(strict_types=1);

namespace App\Domain\Money\Gateways;

/**
 * A parsed webhook callback. Deliberately carries only the identity of the affected payment and the
 * raw payload — the truth of the final status is confirmed by a follow-up `fetchStatus` call (doc 03:
 * "never trust the webhook alone"), never taken from the callback body on faith.
 */
final readonly class GatewayEvent
{
    /**
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public string $externalRef,
        public string $type,
        public GatewayStatus $status,
        public array $raw = [],
    ) {}
}
