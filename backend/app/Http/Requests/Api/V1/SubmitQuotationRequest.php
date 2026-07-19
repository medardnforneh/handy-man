<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use App\Domain\Quotations\QuoteDraft;
use App\Domain\Quotations\QuoteLineInput;
use App\Domain\Quotations\QuoteLineKind;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

/**
 * Body for submitting or revising a quotation (P2.5-01). Additive-only. The subtotal is computed
 * server-side from the lines — a client-supplied total is never trusted.
 */
final class SubmitQuotationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // ownership / job-state checks live in the controller
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.kind' => ['required', new Enum(QuoteLineKind::class)],
            'lines.*.label' => ['required', 'string', 'max:255'],
            'lines.*.quantity' => ['required', 'numeric', 'gt:0'],
            'lines.*.unit_price_minor' => ['required', 'integer', 'min:0'],
            'deposit_minor' => ['sometimes', 'integer', 'min:0'],
            'notes' => ['nullable', 'string'],
            'valid_until' => ['required', 'date', 'after:now'],
            'customer_requested_by' => ['nullable', 'date'],
            'provider_estimated_at' => ['nullable', 'date'],
            'provider_committed_at' => ['nullable', 'date'],
        ];
    }

    public function toDraft(): QuoteDraft
    {
        /** @var array<int, array{kind: string, label: string, quantity: string|int|float, unit_price_minor: int|string}> $lines */
        $lines = $this->input('lines', []);

        return new QuoteDraft(
            depositMinor: (int) $this->integer('deposit_minor'),
            notes: $this->input('notes'),
            validUntil: CarbonImmutable::parse((string) $this->input('valid_until')),
            customerRequestedBy: $this->date('customer_requested_by')?->toImmutable(),
            providerEstimatedAt: $this->date('provider_estimated_at')?->toImmutable(),
            providerCommittedAt: $this->date('provider_committed_at')?->toImmutable(),
            lines: array_map(
                fn (array $l): QuoteLineInput => new QuoteLineInput(
                    kind: QuoteLineKind::from((string) $l['kind']),
                    label: (string) $l['label'],
                    quantity: (string) $l['quantity'],
                    unitPriceMinor: (int) $l['unit_price_minor'],
                ),
                array_values($lines),
            ),
        );
    }
}
