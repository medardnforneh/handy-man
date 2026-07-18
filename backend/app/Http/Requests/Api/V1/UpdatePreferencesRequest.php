<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Update the user's language preferences (build plan P1-05b). `locale` is the UI language;
 * `comms_locale` is the language for reminders/notifications and MAY differ (doc 09).
 */
final class UpdatePreferencesRequest extends FormRequest
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
            'locale' => ['sometimes', 'required', Rule::in(['fr', 'en'])],
            'comms_locale' => ['sometimes', 'required', Rule::in(['fr', 'en'])],
        ];
    }
}
