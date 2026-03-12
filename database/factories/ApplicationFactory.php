<?php

namespace Database\Factories;

use App\Models\Application;
use App\Models\PaymentProvider;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Application>
 */
class ApplicationFactory extends Factory
{
    protected $model = Application::class;

    public function definition(): array
    {
        return [
            'code' => Str::upper($this->faker->unique()->lexify('APP??')),
            'name' => $this->faker->company(),
            'api_key' => hash('sha256', Str::uuid()->toString()),
            'default_provider' => static fn () => PaymentProvider::factory()->create()->code,
            'webhook_url' => $this->faker->url(),
            'webhook_secret' => Str::random(40),
            'status' => true,
        ];
    }
}
