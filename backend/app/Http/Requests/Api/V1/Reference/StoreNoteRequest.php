<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Reference;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Reference FormRequest (P0-05). Validation lives here so the controller stays thin. Rules are
 * additive-only over the life of the API — never tighten an existing rule (CLAUDE.md rule #4).
 */
final class StoreNoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Row-level authorization is the Policy's job; here we only gate on being authenticated.
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'max:2000'],
        ];
    }
}
