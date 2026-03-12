<?php

namespace Database\Factories;

use App\Enums\WebhookDeliveryStatus;
use App\Models\PaymentOrder;
use App\Models\WebhookDelivery;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<WebhookDelivery>
 */
class WebhookDeliveryFactory extends Factory
{
    protected $model = WebhookDelivery::class;

    public function definition(): array
    {
        return [
            'public_id' => 'wh_' . Str::lower((string) Str::ulid()),
            'payment_order_id' => PaymentOrder::factory(),
            'application_id' => static function (array $attributes) {
                return PaymentOrder::query()->findOrFail($attributes['payment_order_id'])->application_id;
            },
            'event_type' => $this->faker->randomElement(['payment.created', 'payment.paid', 'payment.failed']),
            'target_url' => $this->faker->url(),
            'request_body' => ['factory' => true],
            'response_code' => 200,
            'response_body' => 'OK',
            'attempt' => 1,
            'status' => WebhookDeliveryStatus::Success,
            'next_retry_at' => null,
            'created_at' => now(),
        ];
    }
}
