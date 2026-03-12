<?php

namespace Tests\Feature;

use App\Enums\PaymentOrderStatus;
use App\Enums\WebhookDeliveryStatus;
use App\Models\Application;
use App\Models\PaymentEvent;
use App\Models\PaymentMethodMapping;
use App\Models\PaymentOrder;
use App\Models\PaymentProvider;
use App\Models\ProviderTransaction;
use App\Models\WebhookDelivery;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ClientPaymentApiTest extends TestCase
{
    use DatabaseTransactions;

    public function test_client_can_create_lookup_list_and_read_payment_events(): void
    {
        [$application, $headers] = $this->createApiContext();

        $createResponse = $this->withHeaders($headers)->postJson('/api/v1/payments', [
            'external_order_id' => 'INV-2026-001',
            'idempotency_key' => 'idem-inv-2026-001',
            'amount' => 200000,
            'currency' => 'IDR',
            'payment_method' => 'QRIS',
            'customer' => [
                'name' => 'Dimas Prasetio',
                'email' => 'dimas@example.com',
                'phone' => '6281234567890',
            ],
            'metadata' => [
                'product_name' => 'Paket Premium',
            ],
        ]);

        $createResponse->assertCreated();
        $createResponse->assertJsonPath('success', true);
        $createResponse->assertJsonPath('data.application_code', 'BLASKU');
        $createResponse->assertJsonPath('data.status', 'PENDING');
        $createResponse->assertJsonPath('data.payment_method', 'QRIS');
        $createResponse->assertJsonStructure([
            'data' => ['payment_id', 'merchant_ref', 'payment_instruction'],
            'meta' => ['timestamp', 'request_id'],
        ]);

        Http::assertSent(function ($request) use ($application) {
            $expectedSignature = hash_hmac('sha256', $request->body(), $application->webhook_secret);

            return $request->url() === $application->webhook_url
                && $request->method() === 'POST'
                && $request->hasHeader('X-Webhook-Event', 'payment.created')
                && $request->hasHeader('X-Webhook-Signature', $expectedSignature)
                && $request->hasHeader('User-Agent', 'PaymentHub/1.0')
                && str_contains($request->body(), '"event":"payment.created"');
        });

        $paymentId = (string) $createResponse->json('data.payment_id');

        $detailResponse = $this->withHeaders($headers)->getJson('/api/v1/payments/'.$paymentId);
        $detailResponse->assertOk();
        $detailResponse->assertJsonPath('data.external_order_id', 'INV-2026-001');
        $detailResponse->assertJsonPath('data.metadata.product_name', 'Paket Premium');

        $lookupResponse = $this->withHeaders($headers)->getJson('/api/v1/payments/lookup?external_order_id=INV-2026-001');
        $lookupResponse->assertOk();
        $lookupResponse->assertJsonPath('data.payment_id', $paymentId);

        $listResponse = $this->withHeaders($headers)->getJson('/api/v1/payments?status=PENDING&sort_by=created_at&sort_order=desc');
        $listResponse->assertOk();
        $listResponse->assertJsonCount(1, 'data');
        $listResponse->assertJsonPath('data.0.payment_id', $paymentId);
        $listResponse->assertJsonPath('pagination.total', 1);

        $eventResponse = $this->withHeaders($headers)->getJson('/api/v1/payments/'.$paymentId.'/events');
        $eventResponse->assertOk();
        $eventResponse->assertJsonCount(5, 'data');
        $eventResponse->assertJsonFragment(['event_type' => 'payment.created']);
        $eventResponse->assertJsonFragment(['event_type' => 'webhook.dispatched']);
        $eventResponse->assertJsonFragment(['event_type' => 'webhook.success']);

        $deliveryResponse = $this->withHeaders($headers)->getJson('/api/v1/webhook-deliveries?payment_id='.$paymentId);
        $deliveryResponse->assertOk();
        $deliveryResponse->assertJsonCount(1, 'data');
        $deliveryResponse->assertJsonPath('data.0.payment_id', $paymentId);
        $deliveryResponse->assertJsonPath('data.0.status', 'success');
        $deliveryResponse->assertJsonPath('data.0.response_code', 200);
    }

    public function test_create_payment_is_idempotent_when_key_and_payload_match(): void
    {
        [$application, $headers] = $this->createApiContext('idem-secret');

        $payload = [
            'application_code' => $application->code,
            'external_order_id' => 'INV-2026-002',
            'idempotency_key' => 'idem-002',
            'amount' => 125000,
            'currency' => 'IDR',
            'payment_method' => 'QRIS',
            'customer' => [
                'name' => 'Dimas Prasetio',
                'email' => 'dimas@example.com',
                'phone' => '6281234567890',
            ],
        ];

        $firstResponse = $this->withHeaders($headers)->postJson('/api/v1/payments', $payload);
        $secondResponse = $this->withHeaders($headers)->postJson('/api/v1/payments', $payload);

        $firstResponse->assertCreated();
        $secondResponse->assertOk();
        $secondResponse->assertJsonPath('data.payment_id', $firstResponse->json('data.payment_id'));
        $this->assertSame(1, PaymentOrder::query()->count());
    }

    public function test_idempotency_key_is_scoped_per_application(): void
    {
        [$firstApplication, $firstHeaders, $provider] = $this->createApiContext('idem-app-one');

        $secondApplication = Application::factory()->create([
            'code' => 'BLASKU2',
            'name' => 'Blasku Mobile',
            'api_key' => hash('sha256', 'idem-app-two'),
            'default_provider' => $provider->code,
            'webhook_url' => 'https://blasku.test/api/webhook/mobile',
            'webhook_secret' => str_repeat('t', 40),
        ]);

        $sharedPayload = [
            'idempotency_key' => 'shared-idempotency-key',
            'amount' => 125000,
            'currency' => 'IDR',
            'payment_method' => 'QRIS',
            'customer' => [
                'name' => 'Dimas Prasetio',
                'email' => 'dimas@example.com',
                'phone' => '6281234567890',
            ],
        ];

        $firstResponse = $this->withHeaders($firstHeaders)->postJson('/api/v1/payments', array_merge($sharedPayload, [
            'external_order_id' => 'INV-APP-1',
        ]));

        $secondResponse = $this->withHeaders(['X-API-Key' => 'idem-app-two'])->postJson('/api/v1/payments', array_merge($sharedPayload, [
            'external_order_id' => 'INV-APP-2',
        ]));

        $firstResponse->assertCreated();
        $secondResponse->assertCreated();
        $this->assertSame('BLASKU', $firstApplication->code);
        $this->assertNotSame($firstResponse->json('data.payment_id'), $secondResponse->json('data.payment_id'));
        $this->assertSame(2, PaymentOrder::query()->count());
    }

    public function test_create_payment_returns_conflict_when_same_idempotency_key_has_different_payload(): void
    {
        [$application, $headers] = $this->createApiContext('conflict-secret');

        $basePayload = [
            'application_code' => $application->code,
            'external_order_id' => 'INV-2026-003',
            'idempotency_key' => 'idem-003',
            'amount' => 100000,
            'currency' => 'IDR',
            'payment_method' => 'QRIS',
            'customer' => [
                'name' => 'Dimas Prasetio',
                'email' => 'dimas@example.com',
                'phone' => '6281234567890',
            ],
        ];

        $this->withHeaders($headers)->postJson('/api/v1/payments', $basePayload)->assertCreated();

        $conflictResponse = $this->withHeaders($headers)->postJson('/api/v1/payments', array_merge($basePayload, [
            'amount' => 110000,
        ]));

        $conflictResponse->assertStatus(409);
        $conflictResponse->assertJsonPath('success', false);
        $conflictResponse->assertJsonPath('error.code', 'IDEMPOTENCY_CONFLICT');
    }

    public function test_client_cannot_cancel_provider_managed_payment_and_can_retry_failed_webhook_delivery(): void
    {
        [$application, $headers, $provider] = $this->createApiContext('cancel-secret');

        $payment = PaymentOrder::factory()->create([
            'application_id' => $application->id,
            'provider_code' => $provider->code,
            'payment_method' => 'QRIS',
            'status' => PaymentOrderStatus::Pending,
            'customer_phone' => '6281234567890',
        ]);

        ProviderTransaction::factory()->create([
            'payment_order_id' => $payment->id,
            'provider' => $provider->code,
            'merchant_ref' => $payment->merchant_ref,
            'payment_method' => 'QRIS',
        ]);

        $failedDelivery = WebhookDelivery::factory()->create([
            'payment_order_id' => $payment->id,
            'application_id' => $application->id,
            'event_type' => 'payment.created',
            'target_url' => $application->webhook_url,
            'request_body' => [
                'event' => 'payment.created',
                'payment_id' => $payment->public_id,
            ],
            'status' => WebhookDeliveryStatus::Failed,
            'attempt' => 1,
        ]);

        $cancelResponse = $this->withHeaders($headers)->postJson('/api/v1/payments/'.$payment->public_id.'/cancel');
        $cancelResponse->assertStatus(409);
        $cancelResponse->assertJsonPath('success', false);
        $cancelResponse->assertJsonPath('error.code', 'PAYMENT_CANCELLATION_NOT_SUPPORTED');

        $payment->refresh();
        $this->assertSame(PaymentOrderStatus::Pending, $payment->status);

        $retryResponse = $this->withHeaders($headers)->postJson('/api/v1/webhook-deliveries/'.$failedDelivery->public_id.'/retry');
        $retryResponse->assertStatus(202);
        $retryResponse->assertJsonPath('data.id', $failedDelivery->public_id);
        $retryResponse->assertJsonPath('data.status', 'pending');
        $retryResponse->assertJsonPath('data.attempt', 2);

        $failedDelivery->refresh();
        $this->assertSame(WebhookDeliveryStatus::Success, $failedDelivery->status);
        $this->assertSame(200, $failedDelivery->response_code);

        Http::assertSent(fn ($request) => $request->url() === $application->webhook_url
            && $request->hasHeader('X-Webhook-Event', 'payment.created'));
    }

    public function test_tripay_callback_can_mark_payment_paid_and_record_audit_events(): void
    {
        [$application, , $provider] = $this->createApiContext('callback-secret');

        $payment = PaymentOrder::factory()->create([
            'application_id' => $application->id,
            'provider_code' => $provider->code,
            'payment_method' => 'QRIS',
            'status' => PaymentOrderStatus::Pending,
            'customer_phone' => '6281234567890',
            'amount' => 200000,
            'paid_at' => null,
        ]);

        $transaction = ProviderTransaction::factory()->create([
            'payment_order_id' => $payment->id,
            'provider' => $provider->code,
            'merchant_ref' => $payment->merchant_ref,
            'payment_method' => 'QRIS',
            'provider_reference' => null,
            'raw_response' => ['status' => 'accepted'],
        ]);

        $payload = [
            'reference' => 'T1234567890',
            'merchant_ref' => $payment->merchant_ref,
            'payment_method' => 'QRIS',
            'payment_method_code' => 'QRIS',
            'total_amount' => 200000,
            'fee_merchant' => 1400,
            'fee_customer' => 0,
            'total_fee' => 1400,
            'amount_received' => 198600,
            'is_closed_payment' => 1,
            'status' => 'PAID',
            'paid_at' => now()->timestamp,
            'note' => null,
        ];
        $body = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $signature = hash_hmac('sha256', $body, $provider->config['private_key']);

        $response = $this->call(
            'POST',
            '/api/v1/callback/tripay',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_CALLBACK_SIGNATURE' => $signature,
            ],
            $body,
        );

        $response->assertOk();
        $response->assertExactJson([
            'success' => true,
        ]);

        $payment->refresh();
        $transaction->refresh();

        $this->assertSame(PaymentOrderStatus::Paid, $payment->status);
        $this->assertNotNull($payment->paid_at);
        $this->assertSame('T1234567890', $transaction->provider_reference);
        $this->assertNotNull($transaction->paid_at);
        $this->assertDatabaseHas('payment_events', [
            'payment_order_id' => $payment->id,
            'event_type' => 'callback.received',
        ]);
        $this->assertDatabaseHas('payment_events', [
            'payment_order_id' => $payment->id,
            'event_type' => 'payment.paid',
        ]);
        $this->assertDatabaseHas('payment_events', [
            'payment_order_id' => $payment->id,
            'event_type' => 'webhook.success',
        ]);
        $this->assertDatabaseHas('webhook_deliveries', [
            'payment_order_id' => $payment->id,
            'application_id' => $application->id,
            'event_type' => 'payment.paid',
            'status' => WebhookDeliveryStatus::Success->value,
        ]);
    }

    public function test_tripay_callback_still_updates_existing_payment_when_provider_is_inactive(): void
    {
        [$application, , $provider] = $this->createApiContext('inactive-callback-secret');

        $payment = PaymentOrder::factory()->create([
            'application_id' => $application->id,
            'provider_code' => $provider->code,
            'payment_method' => 'QRIS',
            'status' => PaymentOrderStatus::Pending,
            'customer_phone' => '6281234567890',
            'amount' => 200000,
        ]);

        ProviderTransaction::factory()->create([
            'payment_order_id' => $payment->id,
            'provider' => $provider->code,
            'merchant_ref' => $payment->merchant_ref,
            'payment_method' => 'QRIS',
        ]);

        $provider->forceFill(['is_active' => false])->save();

        $payload = [
            'reference' => 'T9999999999',
            'merchant_ref' => $payment->merchant_ref,
            'payment_method' => 'QRIS',
            'total_amount' => 200000,
            'status' => 'PAID',
            'paid_at' => now()->timestamp,
        ];
        $body = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $signature = hash_hmac('sha256', $body, $provider->config['private_key']);

        $response = $this->call(
            'POST',
            '/api/v1/callback/tripay',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_CALLBACK_SIGNATURE' => $signature,
            ],
            $body,
        );

        $response->assertOk();
        $response->assertExactJson(['success' => true]);

        $payment->refresh();
        $this->assertSame(PaymentOrderStatus::Paid, $payment->status);
    }

    public function test_tripay_callback_rejects_invalid_signature(): void
    {
        [$application, , $provider] = $this->createApiContext('invalid-signature-secret');

        $payment = PaymentOrder::factory()->create([
            'application_id' => $application->id,
            'provider_code' => $provider->code,
            'payment_method' => 'QRIS',
            'status' => PaymentOrderStatus::Pending,
            'customer_phone' => '6281234567890',
            'amount' => 150000,
        ]);

        $payload = [
            'reference' => 'T0000000001',
            'merchant_ref' => $payment->merchant_ref,
            'total_amount' => 150000,
            'status' => 'PAID',
            'paid_at' => now()->timestamp,
        ];
        $body = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $response = $this->call(
            'POST',
            '/api/v1/callback/tripay',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_CALLBACK_SIGNATURE' => 'invalid-signature',
            ],
            $body,
        );

        $response->assertStatus(403);
        $response->assertJsonPath('success', false);
        $response->assertJsonPath('error.code', 'INVALID_CALLBACK_SIGNATURE');

        $payment->refresh();
        $this->assertSame(PaymentOrderStatus::Pending, $payment->status);
        $this->assertFalse(PaymentEvent::query()->where('payment_order_id', $payment->id)->where('event_type', 'callback.received')->exists());
    }

    public function test_create_payment_uses_tripay_api_when_credentials_are_available(): void
    {
        Http::fake([
            'https://tripay.test/api/transaction/create' => Http::response([
                'success' => true,
                'message' => 'Success',
                'data' => [
                    'reference' => 'T1234567890',
                    'merchant_ref' => 'BLASKU-20260312-TRIPAY',
                    'payment_method' => 'QRIS',
                    'checkout_url' => 'https://tripay.test/checkout/T1234567890',
                    'qr_string' => '00020101021226690016COM.NOBUBANK.WWW011893600503000008791402145845909650304A60303UMI51440014ID.CO.QRIS.WWW0215ID20254083139570303UMI5204541153033605802ID5920BLASKU PAYMENT HUB6007JAKARTA6105123406304ABCD',
                    'qr_url' => 'https://tripay.test/qr/T1234567890',
                    'pay_code' => null,
                ],
            ], 200),
            'https://blasku.test/*' => Http::response(['received' => true], 200),
        ]);

        $provider = PaymentProvider::query()->updateOrCreate(
            ['code' => 'tripay'],
            [
                'name' => 'Tripay',
                'is_active' => true,
                'config' => [
                    'api_key' => 'tripay-api-key',
                    'merchant_code' => 'TRIPAY',
                    'private_key' => 'tripay-private-key',
                    'api_base_url' => 'https://tripay.test/api',
                    'public_base_url' => 'https://tripay.test',
                ],
            ],
        );

        $application = Application::factory()->create([
            'code' => 'BLASKU',
            'name' => 'Blasku Website',
            'api_key' => hash('sha256', 'provider-secret'),
            'default_provider' => $provider->code,
            'webhook_url' => 'https://blasku.test/api/webhook/payment',
            'webhook_secret' => str_repeat('s', 40),
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
                'min_amount' => 1000,
                'max_amount' => 10000000,
                'is_active' => true,
            ],
        );

        $response = $this->withHeaders(['X-API-Key' => 'provider-secret'])->postJson('/api/v1/payments', [
            'application_code' => $application->code,
            'external_order_id' => 'INV-2026-TRIPAY-001',
            'idempotency_key' => 'idem-tripay-001',
            'amount' => 200000,
            'currency' => 'IDR',
            'payment_method' => 'QRIS',
            'customer' => [
                'name' => 'Dimas Prasetio',
                'email' => 'dimas@example.com',
                'phone' => '6281234567890',
            ],
            'metadata' => [
                'product_name' => 'Paket Premium',
            ],
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.provider', 'tripay');
        $response->assertJsonPath('data.payment_instruction.payment_url', 'https://tripay.test/checkout/T1234567890');
        $response->assertJsonPath('data.payment_instruction.qr_url', 'https://tripay.test/qr/T1234567890');

        $payment = PaymentOrder::query()->firstOrFail();

        Http::assertSent(function ($request) use ($payment) {
            return $request->url() === 'https://tripay.test/api/transaction/create'
                && $request->method() === 'POST'
                && $request->hasHeader('Authorization', 'Bearer tripay-api-key')
                && str_contains($request->body(), 'method=QRIS')
                && str_contains($request->body(), 'merchant_ref='.$payment->merchant_ref);
        });

        $this->assertDatabaseHas('provider_transactions', [
            'payment_order_id' => $payment->id,
            'provider' => 'tripay',
            'provider_reference' => 'T1234567890',
            'payment_url' => 'https://tripay.test/checkout/T1234567890',
            'qr_url' => 'https://tripay.test/qr/T1234567890',
        ]);
    }

    public function test_client_can_sync_payment_status_from_tripay_provider(): void
    {
        Http::fake([
            'https://tripay.test/api/transaction/detail*' => Http::response([
                'success' => true,
                'message' => 'Success',
                'data' => [
                    'reference' => 'T1234567890',
                    'merchant_ref' => 'BLASKU-20260312-TRIPAY',
                    'status' => 'PAID',
                    'amount' => 200000,
                    'paid_at' => now()->timestamp,
                ],
            ], 200),
            'https://blasku.test/*' => Http::response(['received' => true], 200),
        ]);

        $provider = PaymentProvider::query()->updateOrCreate(
            ['code' => 'tripay'],
            [
                'name' => 'Tripay',
                'is_active' => true,
                'config' => [
                    'api_key' => 'tripay-api-key',
                    'merchant_code' => 'TRIPAY',
                    'private_key' => 'tripay-private-key',
                    'api_base_url' => 'https://tripay.test/api',
                    'public_base_url' => 'https://tripay.test',
                ],
            ],
        );

        $application = Application::factory()->create([
            'code' => 'BLASKU',
            'name' => 'Blasku Website',
            'api_key' => hash('sha256', 'provider-sync-secret'),
            'default_provider' => $provider->code,
            'webhook_url' => 'https://blasku.test/api/webhook/payment',
            'webhook_secret' => str_repeat('s', 40),
        ]);

        $payment = PaymentOrder::factory()->create([
            'application_id' => $application->id,
            'provider_code' => $provider->code,
            'payment_method' => 'QRIS',
            'status' => PaymentOrderStatus::Pending,
            'amount' => 200000,
        ]);

        ProviderTransaction::factory()->create([
            'payment_order_id' => $payment->id,
            'provider' => $provider->code,
            'merchant_ref' => $payment->merchant_ref,
            'provider_reference' => 'T1234567890',
            'payment_method' => 'QRIS',
        ]);

        $response = $this->withHeaders(['X-API-Key' => 'provider-sync-secret'])
            ->postJson('/api/v1/payments/'.$payment->public_id.'/sync');

        $response->assertOk();
        $response->assertJsonPath('data.payment_id', $payment->public_id);
        $response->assertJsonPath('data.status', 'PAID');
        $response->assertJsonPath('data.sync.status_changed', true);
        $response->assertJsonPath('data.sync.provider_status', 'PAID');
        $response->assertJsonPath('data.sync.source', 'provider_query');

        $payment->refresh();

        $this->assertSame(PaymentOrderStatus::Paid, $payment->status);
        $this->assertNotNull($payment->paid_at);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://tripay.test/api/transaction/detail?reference=T1234567890'
                && $request->method() === 'GET'
                && $request->hasHeader('Authorization', 'Bearer tripay-api-key');
        });
    }

    public function test_partial_refund_is_rejected(): void
    {
        [$application, $headers, $provider] = $this->createApiContext('partial-refund-secret');

        $payment = PaymentOrder::factory()->create([
            'application_id' => $application->id,
            'provider_code' => $provider->code,
            'payment_method' => 'QRIS',
            'status' => PaymentOrderStatus::Paid,
            'amount' => 200000,
            'paid_at' => now(),
            'customer_phone' => '6281234567890',
        ]);

        ProviderTransaction::factory()->create([
            'payment_order_id' => $payment->id,
            'provider' => $provider->code,
            'merchant_ref' => $payment->merchant_ref,
            'payment_method' => 'QRIS',
        ]);

        $response = $this->withHeaders($headers)->postJson('/api/v1/payments/'.$payment->public_id.'/refund', [
            'amount' => 100000,
            'reason' => 'Customer request',
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('success', false);
        $response->assertJsonPath('error.code', 'VALIDATION_ERROR');
        $response->assertJsonPath('error.details.amount.0', 'Partial refunds are not supported. The refund amount must match the full payment amount.');
    }

    public function test_tripay_refund_is_rejected_until_provider_integration_exists(): void
    {
        [$application, $headers, $provider] = $this->createApiContext('tripay-refund-secret');

        $provider->forceFill([
            'config' => array_merge($provider->config, [
                'supports_refund_api' => true,
            ]),
        ])->save();

        $payment = PaymentOrder::factory()->create([
            'application_id' => $application->id,
            'provider_code' => $provider->code,
            'payment_method' => 'QRIS',
            'status' => PaymentOrderStatus::Paid,
            'amount' => 200000,
            'paid_at' => now(),
            'customer_phone' => '6281234567890',
        ]);

        ProviderTransaction::factory()->create([
            'payment_order_id' => $payment->id,
            'provider' => $provider->code,
            'merchant_ref' => $payment->merchant_ref,
            'payment_method' => 'QRIS',
        ]);

        $response = $this->withHeaders($headers)->postJson('/api/v1/payments/'.$payment->public_id.'/refund', [
            'amount' => 200000,
            'reason' => 'Customer request',
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('success', false);
        $response->assertJsonPath('error.code', 'REFUND_NOT_SUPPORTED');
    }

    public function test_create_payment_fails_when_provider_credentials_are_incomplete(): void
    {
        Http::fake([
            'https://blasku.test/*' => Http::response(['received' => true], 200),
        ]);

        $provider = PaymentProvider::query()->updateOrCreate(
            ['code' => 'tripay'],
            [
                'name' => 'Tripay',
                'is_active' => true,
                'config' => [
                    'merchant_code' => 'TRIPAY',
                    'private_key' => 'tripay-private-key',
                ],
            ],
        );

        $application = Application::factory()->create([
            'code' => 'BLASKU',
            'api_key' => hash('sha256', 'incomplete-provider-secret'),
            'default_provider' => $provider->code,
            'webhook_url' => 'https://blasku.test/api/webhook/payment',
            'webhook_secret' => str_repeat('s', 40),
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
                'min_amount' => 1000,
                'max_amount' => 10000000,
                'is_active' => true,
            ],
        );

        $response = $this->withHeaders(['X-API-Key' => 'incomplete-provider-secret'])->postJson('/api/v1/payments', [
            'external_order_id' => 'INV-INCOMPLETE-001',
            'amount' => 200000,
            'currency' => 'IDR',
            'payment_method' => 'QRIS',
            'customer' => [
                'name' => 'Dimas Prasetio',
                'email' => 'dimas@example.com',
                'phone' => '6281234567890',
            ],
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('success', false);
        $response->assertJsonPath('error.code', 'PROVIDER_CONFIG_INCOMPLETE');
        $this->assertSame(0, PaymentOrder::query()->count());
    }

    private function createApiContext(string $apiKey = 'client-secret'): array
    {
        Http::fake([
            'https://tripay.test/api/transaction/create' => Http::response([
                'success' => true,
                'data' => [
                    'reference' => 'T1234567890',
                    'payment_method' => 'QRIS',
                    'checkout_url' => 'https://tripay.test/checkout/T1234567890',
                    'qr_string' => '000201010212...',
                    'qr_url' => 'https://tripay.test/qr/T1234567890',
                    'pay_code' => null,
                ],
            ], 200),
            'https://blasku.test/*' => Http::response(['received' => true], 200),
        ]);

        $provider = PaymentProvider::query()->updateOrCreate(
            ['code' => 'tripay'],
            [
                'name' => 'Tripay',
                'config' => [
                    'merchant_code' => 'TRIPAY',
                    'api_key' => 'tripay-api-key',
                    'private_key' => 'tripay-private-key',
                    'api_base_url' => 'https://tripay.test/api',
                    'public_base_url' => 'https://tripay.test',
                ],
                'is_active' => true,
            ],
        );

        $application = Application::factory()->create([
            'code' => 'BLASKU',
            'name' => 'Blasku Website',
            'api_key' => hash('sha256', $apiKey),
            'default_provider' => $provider->code,
            'webhook_url' => 'https://blasku.test/api/webhook/payment',
            'webhook_secret' => str_repeat('s', 40),
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
                'min_amount' => 1000,
                'max_amount' => 10000000,
                'is_active' => true,
            ],
        );

        return [$application, ['X-API-Key' => $apiKey], $provider];
    }
}
