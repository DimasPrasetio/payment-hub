<?php

namespace Tests\Feature;

use App\Enums\PaymentOrderStatus;
use App\Enums\WebhookDeliveryStatus;
use App\Models\Application;
use App\Models\PaymentMethodMapping;
use App\Models\PaymentOrder;
use App\Models\PaymentProvider;
use App\Models\ProviderTransaction;
use App\Providers\Midtrans\MidtransClient;
use App\Providers\Xendit\XenditClient;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ClientProviderAdapterTest extends TestCase
{
    use DatabaseTransactions;

    public function test_create_payment_uses_midtrans_adapter_when_provider_is_active(): void
    {
        Http::fake([
            'https://merchant.test/*' => Http::response(['received' => true], 200),
        ]);

        $client = new class extends MidtransClient
        {
            public array $capturedPayload = [];

            public ?string $capturedNotificationUrl = null;

            public function createTransaction(\App\Models\PaymentProvider $provider, array $payload, ?string $idempotencyKey = null, ?string $notificationUrl = null): object
            {
                $this->capturedPayload = $payload;
                $this->capturedNotificationUrl = $notificationUrl;

                return (object) [
                    'token' => 'mid-token-001',
                    'redirect_url' => 'https://app.sandbox.midtrans.com/snap/v2/vtweb/mid-token-001',
                ];
            }
        };

        $this->app->instance(MidtransClient::class, $client);

        $provider = PaymentProvider::query()->updateOrCreate(
            ['code' => 'midtrans'],
            [
                'name' => 'Midtrans',
                'is_active' => true,
                'sandbox_mode' => true,
                'config' => [
                    'server_key' => 'SB-Mid-server-key',
                ],
            ],
        );

        $application = Application::factory()->create([
            'code' => 'BLASKU',
            'api_key' => hash('sha256', 'midtrans-app-key'),
            'default_provider' => $provider->code,
            'webhook_url' => 'https://merchant.test/api/webhook/payment',
            'webhook_secret' => str_repeat('m', 40),
        ]);

        PaymentMethodMapping::query()->updateOrCreate(
            [
                'provider_code' => $provider->code,
                'internal_code' => 'QRIS',
            ],
            [
                'provider_method_code' => 'qris',
                'display_name' => 'QRIS',
                'group' => 'e-wallet',
                'is_active' => true,
            ],
        );

        $response = $this->withHeaders(['X-API-Key' => 'midtrans-app-key'])->postJson('/api/v1/payments', [
            'application_code' => $application->code,
            'external_order_id' => 'INV-MID-001',
            'idempotency_key' => 'idem-mid-001',
            'amount' => 150000,
            'currency' => 'IDR',
            'payment_method' => 'QRIS',
            'customer' => [
                'name' => 'Dimas Prasetio',
                'email' => 'dimas@example.com',
                'phone' => '6281234567890',
            ],
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.provider', 'midtrans');
        $response->assertJsonPath('data.payment_instruction.payment_url', 'https://app.sandbox.midtrans.com/snap/v2/vtweb/mid-token-001');

        $payment = PaymentOrder::query()->firstOrFail();

        $this->assertSame($payment->merchant_ref, $client->capturedPayload['transaction_details']['order_id']);
        $this->assertSame(150000, $client->capturedPayload['transaction_details']['gross_amount']);
        $this->assertSame(['qris'], $client->capturedPayload['enabled_payments']);
        $this->assertStringEndsWith('/api/v1/callback/midtrans', (string) $client->capturedNotificationUrl);

        $this->assertDatabaseHas('provider_transactions', [
            'payment_order_id' => $payment->id,
            'provider' => 'midtrans',
            'provider_reference' => 'mid-token-001',
            'payment_url' => 'https://app.sandbox.midtrans.com/snap/v2/vtweb/mid-token-001',
        ]);
    }

    public function test_midtrans_callback_can_mark_payment_paid(): void
    {
        Http::fake([
            'https://merchant.test/*' => Http::response(['received' => true], 200),
        ]);

        $provider = PaymentProvider::query()->updateOrCreate(
            ['code' => 'midtrans'],
            [
                'name' => 'Midtrans',
                'is_active' => true,
                'config' => [
                    'server_key' => 'SB-Mid-server-key',
                ],
            ],
        );

        $application = Application::factory()->create([
            'code' => 'BLASKU',
            'default_provider' => $provider->code,
            'webhook_url' => 'https://merchant.test/api/webhook/payment',
            'webhook_secret' => str_repeat('m', 40),
        ]);

        $payment = PaymentOrder::factory()->create([
            'application_id' => $application->id,
            'provider_code' => $provider->code,
            'payment_method' => 'QRIS',
            'status' => PaymentOrderStatus::Pending,
            'amount' => 150000,
        ]);

        $transaction = ProviderTransaction::factory()->create([
            'payment_order_id' => $payment->id,
            'provider' => $provider->code,
            'merchant_ref' => $payment->merchant_ref,
            'payment_method' => 'qris',
            'provider_reference' => null,
        ]);

        $payload = [
            'transaction_time' => now()->toDateTimeString(),
            'transaction_status' => 'settlement',
            'transaction_id' => 'trx-mid-001',
            'status_message' => 'midtrans payment notification',
            'status_code' => '200',
            'signature_key' => hash('sha512', $payment->merchant_ref . '200' . '150000.00' . 'SB-Mid-server-key'),
            'payment_type' => 'qris',
            'order_id' => $payment->merchant_ref,
            'gross_amount' => '150000.00',
            'fraud_status' => 'accept',
            'settlement_time' => now()->toDateTimeString(),
        ];

        $response = $this->postJson('/api/v1/callback/midtrans', $payload);

        $response->assertOk();
        $response->assertExactJson([
            'success' => true,
        ]);

        $payment->refresh();
        $transaction->refresh();

        $this->assertSame(PaymentOrderStatus::Paid, $payment->status);
        $this->assertNotNull($payment->paid_at);
        $this->assertSame('trx-mid-001', $transaction->provider_reference);
        $this->assertDatabaseHas('webhook_deliveries', [
            'payment_order_id' => $payment->id,
            'event_type' => 'payment.paid',
            'status' => WebhookDeliveryStatus::Success->value,
        ]);
    }

    public function test_client_can_sync_status_from_midtrans_provider(): void
    {
        Http::fake([
            'https://merchant.test/*' => Http::response(['received' => true], 200),
        ]);

        $client = new class extends MidtransClient
        {
            public function queryTransaction(\App\Models\PaymentProvider $provider, string $merchantRef): object
            {
                return (object) [
                    'transaction_status' => 'settlement',
                    'transaction_id' => 'trx-mid-sync-001',
                    'gross_amount' => '150000.00',
                    'settlement_time' => now()->toDateTimeString(),
                    'fraud_status' => 'accept',
                ];
            }
        };

        $this->app->instance(MidtransClient::class, $client);

        $provider = PaymentProvider::query()->updateOrCreate(
            ['code' => 'midtrans'],
            [
                'name' => 'Midtrans',
                'is_active' => true,
                'config' => [
                    'server_key' => 'SB-Mid-server-key',
                ],
            ],
        );

        $application = Application::factory()->create([
            'code' => 'BLASKU',
            'api_key' => hash('sha256', 'midtrans-sync-key'),
            'default_provider' => $provider->code,
            'webhook_url' => 'https://merchant.test/api/webhook/payment',
            'webhook_secret' => str_repeat('m', 40),
        ]);

        $payment = PaymentOrder::factory()->create([
            'application_id' => $application->id,
            'provider_code' => $provider->code,
            'payment_method' => 'QRIS',
            'status' => PaymentOrderStatus::Pending,
            'amount' => 150000,
        ]);

        ProviderTransaction::factory()->create([
            'payment_order_id' => $payment->id,
            'provider' => $provider->code,
            'merchant_ref' => $payment->merchant_ref,
            'payment_method' => 'qris',
            'provider_reference' => 'mid-token-001',
        ]);

        $response = $this->withHeaders(['X-API-Key' => 'midtrans-sync-key'])
            ->postJson('/api/v1/payments/' . $payment->public_id . '/sync');

        $response->assertOk();
        $response->assertJsonPath('data.status', 'PAID');
        $response->assertJsonPath('data.sync.status_changed', true);
        $response->assertJsonPath('data.sync.provider_status', 'SETTLEMENT');

        $payment->refresh();

        $this->assertSame(PaymentOrderStatus::Paid, $payment->status);
    }

    public function test_create_payment_uses_xendit_adapter_when_provider_is_active(): void
    {
        Http::fake([
            'https://merchant.test/*' => Http::response(['received' => true], 200),
        ]);

        $client = new class extends XenditClient
        {
            public array $capturedPayload = [];

            public function createInvoice(\App\Models\PaymentProvider $provider, array $payload): mixed
            {
                $this->capturedPayload = $payload;

                return [
                    'id' => 'inv-xnd-001',
                    'invoice_url' => 'https://checkout.xendit.co/web/inv-xnd-001',
                    'status' => 'PENDING',
                ];
            }
        };

        $this->app->instance(XenditClient::class, $client);

        $provider = PaymentProvider::query()->updateOrCreate(
            ['code' => 'xendit'],
            [
                'name' => 'Xendit',
                'is_active' => true,
                'config' => [
                    'secret_key' => 'xnd_development_test_key',
                    'callback_token' => 'callback-token',
                ],
            ],
        );

        $application = Application::factory()->create([
            'code' => 'BLASKU',
            'api_key' => hash('sha256', 'xendit-app-key'),
            'default_provider' => $provider->code,
            'webhook_url' => 'https://merchant.test/api/webhook/payment',
            'webhook_secret' => str_repeat('x', 40),
        ]);

        PaymentMethodMapping::query()->updateOrCreate(
            [
                'provider_code' => $provider->code,
                'internal_code' => 'QRIS',
            ],
            [
                'provider_method_code' => 'QRIS',
                'display_name' => 'QRIS',
                'group' => 'e-wallet',
                'is_active' => true,
            ],
        );

        $response = $this->withHeaders(['X-API-Key' => 'xendit-app-key'])->postJson('/api/v1/payments', [
            'application_code' => $application->code,
            'external_order_id' => 'INV-XND-001',
            'idempotency_key' => 'idem-xnd-001',
            'amount' => 225000,
            'currency' => 'IDR',
            'payment_method' => 'QRIS',
            'customer' => [
                'name' => 'Dimas Prasetio',
                'email' => 'dimas@example.com',
                'phone' => '6281234567890',
            ],
            'metadata' => [
                'product_name' => 'Paket Enterprise',
            ],
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.provider', 'xendit');
        $response->assertJsonPath('data.payment_instruction.payment_url', 'https://checkout.xendit.co/web/inv-xnd-001');

        $this->assertSame('QRIS', $client->capturedPayload['payment_methods'][0]);
        $this->assertSame('Paket Enterprise', $client->capturedPayload['description']);

        $payment = PaymentOrder::query()->firstOrFail();

        $this->assertDatabaseHas('provider_transactions', [
            'payment_order_id' => $payment->id,
            'provider' => 'xendit',
            'provider_reference' => 'inv-xnd-001',
            'payment_url' => 'https://checkout.xendit.co/web/inv-xnd-001',
        ]);
    }

    public function test_xendit_callback_can_mark_payment_paid(): void
    {
        Http::fake([
            'https://merchant.test/*' => Http::response(['received' => true], 200),
        ]);

        $provider = PaymentProvider::query()->updateOrCreate(
            ['code' => 'xendit'],
            [
                'name' => 'Xendit',
                'is_active' => true,
                'config' => [
                    'secret_key' => 'xnd_development_test_key',
                    'callback_token' => 'callback-token',
                ],
            ],
        );

        $application = Application::factory()->create([
            'code' => 'BLASKU',
            'default_provider' => $provider->code,
            'webhook_url' => 'https://merchant.test/api/webhook/payment',
            'webhook_secret' => str_repeat('x', 40),
        ]);

        $payment = PaymentOrder::factory()->create([
            'application_id' => $application->id,
            'provider_code' => $provider->code,
            'payment_method' => 'QRIS',
            'status' => PaymentOrderStatus::Pending,
            'amount' => 225000,
        ]);

        $transaction = ProviderTransaction::factory()->create([
            'payment_order_id' => $payment->id,
            'provider' => $provider->code,
            'merchant_ref' => $payment->merchant_ref,
            'payment_method' => 'QRIS',
            'provider_reference' => 'inv-xnd-001',
        ]);

        $response = $this->withHeaders([
            'X-Callback-Token' => 'callback-token',
        ])->postJson('/api/v1/callback/xendit', [
            'id' => 'inv-xnd-001',
            'payment_id' => 'pay-xnd-001',
            'external_id' => $payment->merchant_ref,
            'status' => 'PAID',
            'amount' => 225000,
            'paid_at' => now()->toIso8601String(),
            'payment_method' => 'QRIS',
        ]);

        $response->assertOk();
        $response->assertExactJson([
            'success' => true,
        ]);

        $payment->refresh();
        $transaction->refresh();

        $this->assertSame(PaymentOrderStatus::Paid, $payment->status);
        $this->assertNotNull($payment->paid_at);
        $this->assertSame('pay-xnd-001', $transaction->provider_reference);
        $this->assertDatabaseHas('webhook_deliveries', [
            'payment_order_id' => $payment->id,
            'event_type' => 'payment.paid',
            'status' => WebhookDeliveryStatus::Success->value,
        ]);
    }

    public function test_client_can_sync_status_from_xendit_provider(): void
    {
        Http::fake([
            'https://merchant.test/*' => Http::response(['received' => true], 200),
        ]);

        $client = new class extends XenditClient
        {
            public function getInvoicesByExternalId(\App\Models\PaymentProvider $provider, string $externalId): array
            {
                return [[
                    'id' => 'inv-xnd-001',
                    'external_id' => $externalId,
                    'status' => 'PAID',
                    'amount' => 225000,
                    'paid_at' => now()->toIso8601String(),
                ]];
            }
        };

        $this->app->instance(XenditClient::class, $client);

        $provider = PaymentProvider::query()->updateOrCreate(
            ['code' => 'xendit'],
            [
                'name' => 'Xendit',
                'is_active' => true,
                'config' => [
                    'secret_key' => 'xnd_development_test_key',
                    'callback_token' => 'callback-token',
                ],
            ],
        );

        $application = Application::factory()->create([
            'code' => 'BLASKU',
            'api_key' => hash('sha256', 'xendit-sync-key'),
            'default_provider' => $provider->code,
            'webhook_url' => 'https://merchant.test/api/webhook/payment',
            'webhook_secret' => str_repeat('x', 40),
        ]);

        $payment = PaymentOrder::factory()->create([
            'application_id' => $application->id,
            'provider_code' => $provider->code,
            'payment_method' => 'QRIS',
            'status' => PaymentOrderStatus::Pending,
            'amount' => 225000,
        ]);

        ProviderTransaction::factory()->create([
            'payment_order_id' => $payment->id,
            'provider' => $provider->code,
            'merchant_ref' => $payment->merchant_ref,
            'payment_method' => 'QRIS',
            'provider_reference' => 'inv-xnd-001',
        ]);

        $response = $this->withHeaders(['X-API-Key' => 'xendit-sync-key'])
            ->postJson('/api/v1/payments/' . $payment->public_id . '/sync');

        $response->assertOk();
        $response->assertJsonPath('data.status', 'PAID');
        $response->assertJsonPath('data.sync.status_changed', true);
        $response->assertJsonPath('data.sync.provider_status', 'PAID');

        $payment->refresh();

        $this->assertSame(PaymentOrderStatus::Paid, $payment->status);
    }
}
