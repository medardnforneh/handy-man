<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\PaymentIntent;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin PaymentIntent
 *
 * The pending-UX contract (doc 03): the client lives in `pending`/`processing` gracefully — it shows
 * "check your phone" with a countdown to `expires_at`, and follows `payment_url` for redirect flows.
 */
final class PaymentIntentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $raw = $this->raw ?? [];

        return [
            'id' => $this->id,
            'purpose' => $this->purpose->value,
            'status' => $this->status->value,
            'amount' => ['amount_minor' => $this->amount_minor, 'currency' => $this->currency],
            'msisdn' => $this->msisdn,
            'external_ref' => $this->external_ref,
            'payment_url' => isset($raw['payment_url']) ? (string) $raw['payment_url'] : null,
            'expires_at' => $this->expires_at->toIso8601String(),
            'initiated_at' => $this->initiated_at->toIso8601String(),
            'resolved_at' => $this->resolved_at?->toIso8601String(),
            'failure_code' => $this->failure_code,
        ];
    }
}
