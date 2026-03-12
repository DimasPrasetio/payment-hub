<?php

namespace Tests\Feature;

use App\Models\PaymentProvider;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AdminProviderManagementTest extends TestCase
{
    use DatabaseTransactions;

    public function test_admin_can_manage_provider_credentials_from_detail_panel(): void
    {
        $this->actingAs(User::factory()->create());

        $provider = PaymentProvider::query()->updateOrCreate(
            ['code' => 'tripay'],
            [
                'name' => 'Tripay',
                'config' => [
                    'merchant_code' => 'TRIPAY',
                    'api_key' => 'old-api-key',
                    'private_key' => 'old-private-key',
                    'callback_token' => 'keep-existing-token',
                ],
                'is_active' => true,
                'sandbox_mode' => false,
            ],
        );

        $showResponse = $this->get('/admin/providers/' . $provider->code);

        $showResponse->assertOk();
        $showResponse->assertSee('Edit Credential dan Endpoint Provider');
        $showResponse->assertSee(route('api.callbacks.store', ['provider_code' => $provider->code]), false);

        $updateResponse = $this->put('/admin/providers/' . $provider->code, [
            'code' => $provider->code,
            'name' => 'Tripay Production',
            'is_active' => '1',
            'sandbox_mode' => '1',
            'merchant_code' => 'TRIPAY-PROD',
            'api_key' => 'new-api-key',
            'private_key' => 'new-private-key',
            'client_key' => 'mid-client-key',
            'server_key' => 'mid-server-key',
            'secret_key' => 'xendit-secret-key',
            'callback_token' => '',
            'api_base_url' => 'https://tripay.co.id/api',
            'public_base_url' => 'https://tripay.co.id',
            'return_url' => 'https://merchant.test/payment/return',
            'supports_refund_api' => '1',
            'extra_config' => json_encode([
                'webhook_path' => '/callback/tripay',
                'issuer' => 'tripay',
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        ]);

        $updateResponse->assertRedirect('/admin/providers/' . $provider->code);
        $updateResponse->assertSessionHas('success', 'Konfigurasi provider berhasil diperbarui.');

        $provider->refresh();

        $this->assertSame('Tripay Production', $provider->name);
        $this->assertTrue($provider->is_active);
        $this->assertTrue($provider->sandbox_mode);
        $this->assertSame('TRIPAY-PROD', $provider->config['merchant_code']);
        $this->assertSame('new-api-key', $provider->config['api_key']);
        $this->assertSame('new-private-key', $provider->config['private_key']);
        $this->assertSame('mid-client-key', $provider->config['client_key']);
        $this->assertSame('mid-server-key', $provider->config['server_key']);
        $this->assertSame('xendit-secret-key', $provider->config['secret_key']);
        $this->assertSame('keep-existing-token', $provider->config['callback_token']);
        $this->assertSame('https://tripay.co.id/api', $provider->config['api_base_url']);
        $this->assertSame('https://tripay.co.id', $provider->config['public_base_url']);
        $this->assertSame('https://merchant.test/payment/return', $provider->config['return_url']);
        $this->assertTrue($provider->config['supports_refund_api']);
        $this->assertSame('/callback/tripay', $provider->config['webhook_path']);
        $this->assertSame('tripay', $provider->config['issuer']);

        $rawConfig = (string) DB::table('payment_providers')->where('id', $provider->id)->value('config');

        $this->assertNotSame('', $rawConfig);
        $this->assertStringNotContainsString('new-api-key', $rawConfig);
        $this->assertStringNotContainsString('new-private-key', $rawConfig);
        $this->assertStringNotContainsString('mid-server-key', $rawConfig);
    }

    public function test_provider_activation_requires_minimum_credentials(): void
    {
        $this->actingAs(User::factory()->create());

        $midtrans = PaymentProvider::query()->updateOrCreate(
            ['code' => 'midtrans'],
            ['name' => 'Midtrans', 'config' => [], 'is_active' => false],
        );

        $midtransResponse = $this->from('/admin/providers/midtrans')->put('/admin/providers/midtrans', [
            'code' => 'midtrans',
            'name' => 'Midtrans',
            'is_active' => '1',
            'sandbox_mode' => '1',
        ]);

        $midtransResponse->assertRedirect('/admin/providers/midtrans');
        $midtransResponse->assertSessionHasErrors('server_key');

        $xendit = PaymentProvider::query()->updateOrCreate(
            ['code' => 'xendit'],
            ['name' => 'Xendit', 'config' => [], 'is_active' => false],
        );

        $xenditResponse = $this->from('/admin/providers/xendit')->put('/admin/providers/xendit', [
            'code' => 'xendit',
            'name' => 'Xendit',
            'is_active' => '1',
            'sandbox_mode' => '1',
        ]);

        $xenditResponse->assertRedirect('/admin/providers/xendit');
        $xenditResponse->assertSessionHasErrors([
            'secret_key',
            'callback_token',
        ]);
    }
}
