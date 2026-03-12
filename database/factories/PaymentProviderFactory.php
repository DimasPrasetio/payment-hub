<?php

namespace Database\Factories;

use App\Models\PaymentProvider;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<PaymentProvider>
 */
class PaymentProviderFactory extends Factory
{
    protected $model = PaymentProvider::class;

    public function definition(): array
    {
        return [
            'code' => Str::lower($this->faker->unique()->lexify('provider???')),
            'name' => $this->faker->company(),
            'config' => [
                'merchant_code' => Str::upper($this->faker->bothify('MRC###')),
            ],
            'is_active' => true,
            'sandbox_mode' => false,
        ];
    }
}
