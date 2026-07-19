<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Provider;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreProviderProfileRequest extends FormRequest
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
            'headline' => ['nullable', 'string', 'max:120'],
            'bio' => ['nullable', 'string', 'max:2000'],
            'bio_language' => ['nullable', Rule::in(['fr', 'en'])],
        ];
    }
}
