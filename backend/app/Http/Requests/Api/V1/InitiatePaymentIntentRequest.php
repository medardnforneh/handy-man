<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

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
            // Escrow collection is for a specific engagement.
            'engagement_id' => ['nullable', 'required_if:purpose,escrow', 'uuid', Rule::exists('engagements', 'id')],
        ];
    }

    public function purpose(): PaymentPurpose
    {
        return PaymentPurpose::from((string) $this->input('purpose'));
    }
}
