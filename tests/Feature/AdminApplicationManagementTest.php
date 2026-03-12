<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\PaymentOrder;
use App\Models\PaymentProvider;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AdminApplicationManagementTest extends TestCase
{
    use DatabaseTransactions;

    public function test_admin_can_create_update_rotate_and_delete_application(): void
    {
        $this->actingAs(User::factory()->create());

        $tripay = PaymentProvider::query()->updateOrCreate(
            ['code' => 'tripay'],
            ['name' => 'Tripay', 'is_active' => true],
        );

        $midtrans = PaymentProvider::query()->updateOrCreate(
            ['code' => 'midtrans'],
            ['name' => 'Midtrans', 'is_active' => true],
        );

        $createPage = $this->get('/admin/applications/create');
        $createPage->assertOk();
        $createPage->assertSee('Daftarkan aplikasi baru');

        $storeResponse = $this->post('/admin/applications', [
            'code' => 'BLASKU',
            'name' => 'Blasku Website',
            'default_provider' => $tripay->code,
            'webhook_url' => 'https://blasku.test/api/webhook/payment',
            'status' => '1',
        ]);

        $storeResponse->assertRedirect('/admin/applications/BLASKU');
        $storeResponse->assertSessionHas('success', 'Aplikasi baru berhasil dibuat.');
        $storeResponse->assertSessionHas('issued_credentials');

        $issuedCredentials = app('session.store')->get('issued_credentials');

        $this->assertIsArray($issuedCredentials);
        $this->assertArrayHasKey('api_key', $issuedCredentials);
        $this->assertArrayHasKey('webhook_secret', $issuedCredentials);

        $application = Application::query()->where('code', 'BLASKU')->firstOrFail();

        $this->assertSame('Blasku Website', $application->name);
        $this->assertSame($tripay->code, $application->default_provider);
        $this->assertSame('https://blasku.test/api/webhook/payment', $application->webhook_url);
        $this->assertTrue($application->status);
        $this->assertSame($issuedCredentials['webhook_secret'], $application->webhook_secret);

        $rawApplication = DB::table('applications')->where('id', $application->id)->first();

        $this->assertNotSame($issuedCredentials['api_key'], $rawApplication->api_key);
        $this->assertNotSame($issuedCredentials['webhook_secret'], $rawApplication->webhook_secret);
        $this->assertStringNotContainsString($issuedCredentials['webhook_secret'], $rawApplication->webhook_secret);

        $apiResponse = $this->withHeaders(['X-API-Key' => $issuedCredentials['api_key']])->getJson('/api/v1/providers');
        $apiResponse->assertOk();
        $apiResponse->assertJsonPath('success', true);

        $updateResponse = $this->put('/admin/applications/' . $application->code, [
            'code' => $application->code,
            'name' => 'Blasku Mobile',
            'default_provider' => $midtrans->code,
            'webhook_url' => 'https://mobile.blasku.test/api/webhook/payment',
            'status' => '1',
        ]);

        $updateResponse->assertRedirect('/admin/applications/' . $application->code);
        $updateResponse->assertSessionHas('success', 'Konfigurasi aplikasi berhasil diperbarui.');

        $application->refresh();
        $this->assertSame('Blasku Mobile', $application->name);
        $this->assertSame($midtrans->code, $application->default_provider);
        $this->assertSame('https://mobile.blasku.test/api/webhook/payment', $application->webhook_url);

        $oldApiKey = $issuedCredentials['api_key'];

        $rotateApiKeyResponse = $this->post('/admin/applications/' . $application->code . '/rotate-api-key');
        $rotateApiKeyResponse->assertRedirect('/admin/applications/' . $application->code);
        $rotateApiKeyResponse->assertSessionHas('issued_credentials');

        $rotatedApiCredentials = app('session.store')->get('issued_credentials');

        $this->assertArrayHasKey('api_key', $rotatedApiCredentials);
        $this->assertNotSame($oldApiKey, $rotatedApiCredentials['api_key']);

        $oldKeyResponse = $this->withHeaders(['X-API-Key' => $oldApiKey])->getJson('/api/v1/providers');
        $oldKeyResponse->assertStatus(401);

        $newKeyResponse = $this->withHeaders(['X-API-Key' => $rotatedApiCredentials['api_key']])->getJson('/api/v1/providers');
        $newKeyResponse->assertOk();

        $oldWebhookSecret = $application->webhook_secret;

        $rotateWebhookResponse = $this->post('/admin/applications/' . $application->code . '/rotate-webhook-secret');
        $rotateWebhookResponse->assertRedirect('/admin/applications/' . $application->code);
        $rotateWebhookResponse->assertSessionHas('issued_credentials');

        $rotatedWebhookCredentials = app('session.store')->get('issued_credentials');

        $application->refresh();

        $this->assertArrayHasKey('webhook_secret', $rotatedWebhookCredentials);
        $this->assertNotSame($oldWebhookSecret, $rotatedWebhookCredentials['webhook_secret']);
        $this->assertSame($rotatedWebhookCredentials['webhook_secret'], $application->webhook_secret);

        $deleteResponse = $this->delete('/admin/applications/' . $application->code);
        $deleteResponse->assertRedirect('/admin/applications');
        $deleteResponse->assertSessionHas('success', 'Aplikasi berhasil dihapus.');
        $this->assertDatabaseMissing('applications', [
            'id' => $application->id,
        ]);
    }

    public function test_admin_cannot_delete_application_that_has_transactions(): void
    {
        $this->actingAs(User::factory()->create());

        $provider = PaymentProvider::query()->updateOrCreate(
            ['code' => 'tripay'],
            ['name' => 'Tripay', 'is_active' => true],
        );

        $application = Application::factory()->create([
            'code' => 'BLASKU',
            'default_provider' => $provider->code,
        ]);

        PaymentOrder::factory()->create([
            'application_id' => $application->id,
            'provider_code' => $provider->code,
        ]);

        $response = $this->delete('/admin/applications/' . $application->code);

        $response->assertRedirect('/admin/applications/' . $application->code);
        $response->assertSessionHas('error', 'Aplikasi tidak dapat dihapus karena sudah memiliki transaksi atau riwayat webhook.');
        $this->assertDatabaseHas('applications', [
            'id' => $application->id,
        ]);
    }

    public function test_admin_cannot_create_application_with_inactive_provider(): void
    {
        $this->actingAs(User::factory()->create());

        $inactiveProvider = PaymentProvider::query()->updateOrCreate(
            ['code' => 'xendit'],
            ['name' => 'Xendit', 'is_active' => false],
        );

        $response = $this->from('/admin/applications/create')->post('/admin/applications', [
            'code' => 'BLASKU',
            'name' => 'Blasku Website',
            'default_provider' => $inactiveProvider->code,
            'webhook_url' => 'https://blasku.test/api/webhook/payment',
            'status' => '1',
        ]);

        $response->assertRedirect('/admin/applications/create');
        $response->assertSessionHasErrors('default_provider');
        $this->assertDatabaseMissing('applications', [
            'code' => 'BLASKU',
        ]);
    }
}
