<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class BlockPartyRequest extends FormRequest
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
            'blocked_party_id' => [
                'required', 'uuid', Rule::exists('parties', 'id'),
                Rule::notIn([$this->user()?->party_id]), // can't block yourself
            ],
        ];
    }
}
