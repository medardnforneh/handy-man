<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A client may only post free-form messages. `kind`, if given, must be `text` — a structured kind
 * (e.g. `quote_accepted`) is rejected here (build plan P4-02): the server narrates those, never the
 * client.
 */
final class PostMessageRequest extends FormRequest
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
            'body' => ['required', 'string', 'max:5000'],
            'kind' => ['sometimes', Rule::in(['text'])],
            'reply_to_id' => ['nullable', 'uuid', Rule::exists('messages', 'id')],
        ];
    }
}
