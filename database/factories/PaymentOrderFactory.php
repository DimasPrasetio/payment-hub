<?php

namespace Database\Factories;

use App\Enums\PaymentOrderStatus;
use App\Models\Application;
use App\Models\PaymentOrder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<PaymentOrder>
 */
class PaymentOrderFactory extends Factory
{
    protected $model = PaymentOrder::class;

    public function definition(): array
    {
        return [
            'public_id' => 'pay_' . Str::lower((string) Str::ulid()),
            'application_id' => Application::factory(),
            'tenant_id' => $this->faker->optional()->uuid(),
            'external_order_id' => Str::upper($this->faker->unique()->bothify('INV-####')),
            'idempotency_key' => Str::uuid()->toString(),
            'merchant_ref' => Str::upper($this->faker->unique()->bothify('APP-########-????')),
            'provider_code' => static function (array $attributes) {
                return Application::query()->findOrFail($attributes['application_id'])->default_provider;
            },
            'payment_method' => $this->faker->randomElement(['QRIS', 'BANK_TRANSFER_BCA', 'BANK_TRANSFER_BNI']),
            'customer_name' => $this->faker->name(),
            'customer_email' => $this->faker->safeEmail(),
            'customer_phone' => $this->faker->phoneNumber(),
            'amount' => $this->faker->numberBetween(10000, 500000),
            'currency' => 'IDR',
            'status' => PaymentOrderStatus::Pending,
            'metadata' => [
                'source' => 'factory',
            ],
            'paid_at' => null,
            'expires_at' => now()->addHour(),
        ];
    }

    public function paid(): self
    {
        return $this->state(fn () => [
            'status' => PaymentOrderStatus::Paid,
            'paid_at' => now()->subMinutes(5),
        ]);
    }
}
