<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ReportPartyRequest extends FormRequest
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
            'subject_party_id' => [
                'required', 'uuid', Rule::exists('parties', 'id'),
                Rule::notIn([$this->user()?->party_id]), // can't report yourself
            ],
            'category' => ['required', 'in:fraud,no_show,harassment,safety,spam,off_platform,other'],
            'body' => ['required', 'string', 'max:5000'],
            'job_id' => ['nullable', 'uuid', Rule::exists('service_jobs', 'id')],
        ];
    }
}
