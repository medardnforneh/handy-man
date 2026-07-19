<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

final class ReviewDeliverableRequest extends FormRequest
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
            'decision' => ['required', 'in:accept,reject'],
            'reject_reason' => ['nullable', 'required_if:decision,reject', 'string', 'max:2000'],
        ];
    }

    public function isAccept(): bool
    {
        return $this->input('decision') === 'accept';
    }
}
