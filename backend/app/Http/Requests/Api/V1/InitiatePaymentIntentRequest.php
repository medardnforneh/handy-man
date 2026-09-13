<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use App\Domain\Money\PaymentMethod;
use App\Domain\Money\PaymentPurpose;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

final class InitiatePaymentIntentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'purpose' => ['required', new Enum(PaymentPurpose::class)],
            'amount_minor' => ['required', 'integer', 'min:1'],
            'msisdn' => ['required', 'string', 'max:20'],
            // The rail. Optional: absent, the number's prefix decides; present, it must be a mobile one.
            'method' => ['nullable', Rule::in(array_map(fn (PaymentMethod $m): string => $m->value, PaymentMethod::mobileRails()))],
            // Escrow collection is for a specific engagement.
            'engagement_id' => ['nullable', 'required_if:purpose,escrow', 'uuid', Rule::exists('engagements', 'id')],
        ];
    }

    public function paymentMethod(): ?PaymentMethod
    {
        $raw = $this->input('method');

        return is_string($raw) && $raw !== '' ? PaymentMethod::from($raw) : null;
    }

    public function purpose(): PaymentPurpose
    {
        return PaymentPurpose::from((string) $this->input('purpose'));
    }
}
