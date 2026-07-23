<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

final class IssueWarrantyRequest extends FormRequest
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
            'duration_days' => ['required', 'integer', 'min:1', 'max:3650'],
            'terms' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
