<?php

namespace Database\Factories;

use App\Models\PaymentMethodMapping;
use App\Models\PaymentProvider;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<PaymentMethodMapping>
 */
class PaymentMethodMappingFactory extends Factory
{
    protected $model = PaymentMethodMapping::class;

    public function definition(): array
    {
        return [
            'internal_code' => Str::upper($this->faker->unique()->lexify('METHOD_???')),
            'provider_code' => static fn () => PaymentProvider::factory()->create()->code,
            'provider_method_code' => Str::upper($this->faker->lexify('CODE???')),
            'display_name' => $this->faker->words(2, true),
            'group' => $this->faker->randomElement(['bank_transfer', 'e_wallet', 'card']),
            'icon_url' => $this->faker->imageUrl(),
            'fee_flat' => $this->faker->numberBetween(0, 5000),
            'fee_percent' => $this->faker->randomFloat(2, 0, 4),
            'min_amount' => 10000,
            'max_amount' => 5000000,
            'is_active' => true,
        ];
    }
}
