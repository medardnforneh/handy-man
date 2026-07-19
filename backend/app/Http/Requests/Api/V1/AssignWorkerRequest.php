<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use App\Domain\Engagements\AssignmentRole;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

final class AssignWorkerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // dispatch authority is checked via EngagementPolicy in the controller
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'worker_user_id' => ['required', 'uuid', Rule::exists('users', 'id')],
            'role' => ['sometimes', new Enum(AssignmentRole::class)],
            // A scheduling window is optional, but both bounds go together and must be ordered.
            'scheduled_from' => ['nullable', 'date', 'required_with:scheduled_to'],
            'scheduled_to' => ['nullable', 'date', 'required_with:scheduled_from', 'after:scheduled_from'],
        ];
    }

    public function role(): AssignmentRole
    {
        $value = $this->input('role');

        return $value !== null ? AssignmentRole::from((string) $value) : AssignmentRole::Helper;
    }

    public function scheduledFrom(): ?CarbonImmutable
    {
        $value = $this->input('scheduled_from');

        return $value !== null ? CarbonImmutable::parse((string) $value) : null;
    }

    public function scheduledTo(): ?CarbonImmutable
    {
        $value = $this->input('scheduled_to');

        return $value !== null ? CarbonImmutable::parse((string) $value) : null;
    }
}
