<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Geo payload shared by check-in and check-out (build plan P5-03). Coordinates are optional — a
 * device may check in without a GPS fix — but latitude and longitude travel together.
 */
final class WorkSessionRequest extends FormRequest
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
            'latitude' => ['nullable', 'required_with:longitude', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'required_with:latitude', 'numeric', 'between:-180,180'],
            'accuracy_m' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    public function latitude(): ?float
    {
        return $this->filled('latitude') ? (float) $this->input('latitude') : null;
    }

    public function longitude(): ?float
    {
        return $this->filled('longitude') ? (float) $this->input('longitude') : null;
    }

    public function accuracyM(): ?float
    {
        return $this->filled('accuracy_m') ? (float) $this->input('accuracy_m') : null;
    }
}
