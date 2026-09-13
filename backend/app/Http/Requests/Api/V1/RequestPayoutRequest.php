<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use App\Domain\Money\PaymentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class RequestPayoutRequest extends FormRequest
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
            'amount_minor' => ['required', 'integer', 'min:1'],
            'msisdn' => ['required', 'string', 'max:20'],
            'method' => ['nullable', Rule::in(array_map(fn (PaymentMethod $m): string => $m->value, PaymentMethod::mobileRails()))],
        ];
    }
}
