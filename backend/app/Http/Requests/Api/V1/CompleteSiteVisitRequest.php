<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class CompleteSiteVisitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // ownership checked in the controller
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'outcome_notes' => ['nullable', 'string'],
            'resulting_quotation_id' => ['nullable', 'uuid', Rule::exists('quotations', 'id')],
        ];
    }
}
