<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use App\Domain\Execution\ProviderStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

/**
 * A worker's structured status signal (build plan P5-06). `arrived` is not accepted here — it is
 * emitted only by the geo check-in endpoint.
 */
final class RecordStatusRequest extends FormRequest
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
            'status' => ['required', new Enum(ProviderStatus::class)],
        ];
    }

    public function status(): ProviderStatus
    {
        return ProviderStatus::from($this->string('status')->toString());
    }
}
