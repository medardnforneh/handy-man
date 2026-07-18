<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class VerifyOtpRequest extends FormRequest
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
            'phone_e164' => ['required', 'string', 'regex:/^\+[1-9]\d{6,14}$/'],
            'code' => ['required', 'string'],
            'purpose' => ['required', Rule::in(config('otp.purposes'))],
        ];
    }
}
