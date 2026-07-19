<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Quotation;
use App\Models\QuotationLine;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<QuotationLine>
 */
final class QuotationLineFactory extends Factory
{
    protected $model = QuotationLine::class;

    /**
     * @return array<model-property<QuotationLine>, mixed>
     */
    public function definition(): array
    {
        return [
            'quotation_id' => Quotation::factory(),
            'position' => 0,
            'kind' => 'labour',
            'label' => fake()->words(3, true),
            'quantity' => '1.000',
            'unit_price_minor' => 500000,
        ];
    }
}
