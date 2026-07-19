<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;

final class ScheduleSiteVisitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // job-state check lives in the controller; the visitor is the caller
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'scheduled_for' => ['required', 'date', 'after:now'],
            'is_chargeable' => ['sometimes', 'boolean'],
            'fee_minor' => ['required_if:is_chargeable,true', 'integer', 'min:1'],
        ];
    }

    public function scheduledFor(): CarbonImmutable
    {
        return CarbonImmutable::parse((string) $this->input('scheduled_for'));
    }

    public function isChargeable(): bool
    {
        return $this->boolean('is_chargeable');
    }

    public function feeMinor(): int
    {
        return (int) $this->integer('fee_minor');
    }
}
