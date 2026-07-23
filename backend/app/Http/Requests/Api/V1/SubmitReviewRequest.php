<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A review submission (build plan P6-08). The rating is required; the body and a private note (for
 * the subject's eyes only) are optional. `subject_worker_user_id` names the specific worker when the
 * provider is an org.
 */
final class SubmitReviewRequest extends FormRequest
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
            'rating' => ['required', 'integer', 'between:1,5'],
            'body' => ['nullable', 'string', 'max:5000'],
            'private_note' => ['nullable', 'string', 'max:5000'],
            'subject_worker_user_id' => ['nullable', 'uuid', Rule::exists('users', 'id')],
        ];
    }
}
