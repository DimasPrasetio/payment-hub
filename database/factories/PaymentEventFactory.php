<?php

namespace Database\Factories;

use App\Models\PaymentEvent;
use App\Models\PaymentOrder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<PaymentEvent>
 */
class PaymentEventFactory extends Factory
{
    protected $model = PaymentEvent::class;

    public function definition(): array
    {
        return [
            'public_id' => 'evt_' . Str::lower((string) Str::ulid()),
            'payment_order_id' => PaymentOrder::factory(),
            'event_type' => $this->faker->randomElement(['payment.created', 'provider.response', 'callback.received']),
            'payload' => ['factory' => true],
            'created_at' => now(),
        ];
    }
}
