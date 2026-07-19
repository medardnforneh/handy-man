<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\PaymentEvent;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<PaymentEvent>
 */
final class PaymentEventFactory extends Factory
{
    protected $model = PaymentEvent::class;

    /**
     * @return array<model-property<PaymentEvent>, mixed>
     */
    public function definition(): array
    {
        return [
            'gateway' => 'fake',
            'external_ref' => (string) Str::uuid(),
            'event_type' => 'fake.notification',
            'signature_valid' => true,
            'payload' => ['reference' => (string) Str::uuid()],
            'received_at' => now(),
        ];
    }
}
