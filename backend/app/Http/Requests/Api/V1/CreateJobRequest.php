<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class CreateJobRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'skill_id' => ['required', 'uuid', Rule::exists('skills', 'id')->where('is_leaf', true)],
            'engagement_mode' => ['required', Rule::in(['onsite', 'remote', 'hybrid'])],
            'title' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:4000'],
            'description_language' => ['nullable', Rule::in(['fr', 'en'])],
            // Address is required unless the work is remote, and must belong to the caller's party.
            'address_id' => [
                'required_unless:engagement_mode,remote', 'nullable', 'uuid',
                Rule::exists('addresses', 'id')->where('party_id', $this->user()?->party_id),
            ],
            'price_model' => ['nullable', Rule::in(['hourly', 'fixed', 'quote_only'])],
            'budget_minor' => ['nullable', 'integer', 'min:0'],
            'urgency' => ['nullable', 'integer', 'between:1,3'],
            'photos' => ['nullable', 'array', 'max:10'],
            'photos.*' => ['string', 'max:512'],
        ];
    }
}
