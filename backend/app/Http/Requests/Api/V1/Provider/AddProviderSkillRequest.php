<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Provider;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class AddProviderSkillRequest extends FormRequest
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
            'price_model' => ['required', Rule::in(['hourly', 'fixed', 'quote_only'])],
            'rate_minor' => ['nullable', 'integer', 'min:0'],
            'years_experience' => ['nullable', 'integer', 'min:0', 'max:80'],
        ];
    }
}
