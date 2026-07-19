<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\LedgerAccount;
use App\Models\Party;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LedgerAccount>
 */
final class LedgerAccountFactory extends Factory
{
    protected $model = LedgerAccount::class;

    /**
     * @return array<model-property<LedgerAccount>, mixed>
     */
    public function definition(): array
    {
        return [
            'party_id' => null,
            'kind' => 'platform_cash',
            'currency' => 'XAF',
        ];
    }

    public function ofKind(string $kind): static
    {
        return $this->state(fn () => ['kind' => $kind]);
    }

    public function forParty(Party $party): static
    {
        return $this->state(fn () => ['party_id' => $party->id]);
    }
}
