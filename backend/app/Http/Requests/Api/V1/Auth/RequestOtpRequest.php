<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class RequestOtpRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // public: this IS the authentication entry point
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // E.164: leading +, up to 15 digits.
            'phone_e164' => ['required', 'string', 'regex:/^\+[1-9]\d{6,14}$/'],
            'purpose' => ['required', Rule::in(config('otp.purposes'))],
        ];
    }
}
