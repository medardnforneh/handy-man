<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class RegisterDeviceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * The device id comes from the X-Device-Id header; fold it in so validation can require it.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'device_id' => $this->header(config('api.device_id_header')),
            'app_version' => $this->input('app_version', $this->header(config('api.app_version_header'))),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'device_id' => ['required', 'uuid'],
            'platform' => ['required', Rule::in(['android', 'ios', 'web'])],
            'push_token' => ['nullable', 'string', 'max:512'],
            'app_version' => ['required', 'string', 'regex:/^\d+\.\d+\.\d+$/'],
        ];
    }
}
