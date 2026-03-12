<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\PaymentMethodMapping;
use App\Models\PaymentProvider;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class PaymentMethodApiTest extends TestCase
{
    use DatabaseTransactions;

    public function test_payment_methods_endpoint_returns_contract_payload_for_authenticated_application(): void
    {
        $apiKey = 'client-secret';

        $provider = PaymentProvider::query()->updateOrCreate(
            ['code' => 'tripay'],
            ['name' => 'Tripay', 'is_active' => true],
        );

        Application::factory()->create([
            'code' => 'BLASKU',
            'default_provider' => $provider->code,
            'api_key' => hash('sha256', $apiKey),
        ]);

        PaymentMethodMapping::query()
            ->where('provider_code', $provider->code)
            ->update(['is_active' => false]);

        PaymentMethodMapping::query()->updateOrCreate(
            [
                'provider_code' => $provider->code,
                'internal_code' => 'QRIS',
            ],
            [
                'provider_method_code' => 'QRIS',
                'display_name' => 'QRIS',
                'group' => 'e-wallet',
                'min_amount' => 10000,
                'max_amount' => 500000,
                'is_active' => true,
            ],
        );

        PaymentMethodMapping::query()->updateOrCreate(
            [
                'provider_code' => $provider->code,
                'internal_code' => 'INACTIVE',
            ],
            [
                'provider_method_code' => 'NONE',
                'display_name' => 'Inactive',
                'is_active' => false,
            ],
        );

        $inactiveProvider = PaymentProvider::query()->updateOrCreate(
            ['code' => 'midtrans'],
            ['name' => 'Midtrans', 'is_active' => false],
        );

        PaymentMethodMapping::query()->updateOrCreate(
            [
                'provider_code' => $inactiveProvider->code,
                'internal_code' => 'QRIS_MID',
            ],
            [
                'provider_method_code' => 'qris',
                'display_name' => 'QRIS Midtrans',
                'group' => 'e-wallet',
                'min_amount' => 10000,
                'max_amount' => 500000,
                'is_active' => true,
            ],
        );

        $response = $this->withHeaders([
            'X-API-Key' => $apiKey,
        ])->getJson('/api/v1/payment-methods?provider_code=tripay&active_only=true&amount=150000');

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.code', 'QRIS');
        $response->assertJsonPath('data.0.provider', 'tripay');
        $response->assertJsonPath('data.0.is_active', true);
        $response->assertJsonMissing(['provider' => 'midtrans']);
        $response->assertJsonStructure([
            'meta' => ['timestamp', 'request_id'],
        ]);
    }
}
