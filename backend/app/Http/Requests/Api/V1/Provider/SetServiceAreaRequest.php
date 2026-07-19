<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Provider;

use Illuminate\Foundation\Http\FormRequest;

final class SetServiceAreaRequest extends FormRequest
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
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'radius_m' => ['required', 'integer', 'between:500,100000'],
        ];
    }
}
