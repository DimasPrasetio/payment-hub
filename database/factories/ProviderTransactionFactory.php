<?php

namespace Database\Factories;

use App\Models\PaymentOrder;
use App\Models\ProviderTransaction;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProviderTransaction>
 */
class ProviderTransactionFactory extends Factory
{
    protected $model = ProviderTransaction::class;

    public function definition(): array
    {
        return [
            'payment_order_id' => PaymentOrder::factory(),
            'provider' => static function (array $attributes) {
                return PaymentOrder::query()->findOrFail($attributes['payment_order_id'])->provider_code;
            },
            'merchant_ref' => static function (array $attributes) {
                return PaymentOrder::query()->findOrFail($attributes['payment_order_id'])->merchant_ref;
            },
            'provider_reference' => $this->faker->uuid(),
            'payment_method' => $this->faker->randomElement(['QRIS', 'BRIVA', 'BNIVA']),
            'payment_url' => $this->faker->optional()->url(),
            'pay_code' => $this->faker->optional()->numerify('##########'),
            'qr_string' => $this->faker->optional()->sha1(),
            'qr_url' => $this->faker->optional()->url(),
            'raw_request' => ['factory' => true],
            'raw_response' => ['status' => 'ok'],
            'paid_at' => null,
        ];
    }
}
