<?php

namespace Tests\Feature;

use App\Enums\WebhookDeliveryStatus;
use App\Models\Application;
use App\Models\PaymentOrder;
use App\Models\PaymentProvider;
use App\Models\WebhookDelivery;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WebhookRetryCommandTest extends TestCase
{
    use DatabaseTransactions;

    public function test_retry_due_command_queues_and_delivers_due_webhooks(): void
    {
        Http::fake([
            'https://merchant.test/*' => Http::response(['received' => true], 200),
        ]);

        $provider = PaymentProvider::query()->updateOrCreate(
            ['code' => 'tripay'],
            ['name' => 'Tripay', 'is_active' => true],
        );

        $application = Application::factory()->create([
            'code' => 'BLASKU',
            'default_provider' => $provider->code,
            'webhook_url' => 'https://merchant.test/api/webhook/payment',
            'webhook_secret' => str_repeat('w', 40),
        ]);

        $payment = PaymentOrder::factory()->create([
            'application_id' => $application->id,
            'provider_code' => $provider->code,
        ]);

        $delivery = WebhookDelivery::factory()->create([
            'payment_order_id' => $payment->id,
            'application_id' => $application->id,
            'event_type' => 'payment.failed',
            'target_url' => $application->webhook_url,
            'request_body' => [
                'event' => 'payment.failed',
                'payment_id' => $payment->public_id,
            ],
            'status' => WebhookDeliveryStatus::Failed,
            'attempt' => 1,
            'response_code' => 500,
            'response_body' => 'Gateway timeout',
            'next_retry_at' => now()->subMinute(),
        ]);

        $this->artisan('webhook-deliveries:retry-due', ['--limit' => 10])
            ->assertExitCode(0);

        $delivery->refresh();

        $this->assertSame(WebhookDeliveryStatus::Success, $delivery->status);
        $this->assertSame(2, $delivery->attempt);
        $this->assertSame(200, $delivery->response_code);

        Http::assertSent(fn ($request) => $request->url() === $application->webhook_url
            && $request->hasHeader('X-Webhook-Event', 'payment.failed'));
    }
}
