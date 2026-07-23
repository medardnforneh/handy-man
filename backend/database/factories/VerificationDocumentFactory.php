<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Verification\DocKind;
use App\Domain\Verification\DocStatus;
use App\Models\Party;
use App\Models\VerificationDocument;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<VerificationDocument>
 */
final class VerificationDocumentFactory extends Factory
{
    protected $model = VerificationDocument::class;

    /**
     * @return array<model-property<VerificationDocument>, mixed>
     */
    public function definition(): array
    {
        return [
            'party_id' => Party::factory()->individual(),
            'kind' => DocKind::NationalIdFront->value,
            'storage_path' => 'documents/'.Str::uuid()->toString().'.enc',
            'sha256' => hash('sha256', Str::random()),
            'grants_tier' => 2,
            'status' => DocStatus::Pending->value,
        ];
    }

    public function approved(int $tier = 2): static
    {
        return $this->state(fn () => [
            'status' => DocStatus::Approved->value,
            'grants_tier' => $tier,
            'reviewed_at' => now(),
        ]);
    }
}
