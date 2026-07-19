<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use App\Domain\Engagements\AssignmentRole;
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
        ];
    }

    public function role(): AssignmentRole
    {
        $value = $this->input('role');

        return $value !== null ? AssignmentRole::from((string) $value) : AssignmentRole::Helper;
    }
}
