<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A panic alert (build plan P6-04). Coordinates are optional (a fix may not be available) but travel
 * together; an optional assignment ties the alert to the job the user is on.
 */
final class PanicRequest extends FormRequest
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
            'note' => ['nullable', 'string', 'max:2000'],
            'assignment_id' => ['nullable', 'uuid', Rule::exists('assignments', 'id')],
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
}
