<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class RecordConsentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        // Default the presented locale to the user's UI locale — the client should send the locale
        // the policy was actually shown in, but the user's locale is the safe fallback.
        if (! $this->has('presented_locale')) {
            $this->merge(['presented_locale' => $this->user()?->getAttribute('locale')]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'purpose' => ['required', Rule::in(config('consent.purposes'))],
            'granted' => ['required', 'boolean'],
            'presented_locale' => ['required', Rule::in(['fr', 'en'])],
        ];
    }
}
