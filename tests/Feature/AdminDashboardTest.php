<?php

namespace Tests\Feature;

use App\Enums\PaymentOrderStatus;
use App\Enums\WebhookDeliveryStatus;
use App\Models\Application;
use App\Models\PaymentEvent;
use App\Models\PaymentOrder;
use App\Models\PaymentProvider;
use App\Models\ProviderTransaction;
use App\Models\User;
use App\Models\WebhookDelivery;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class AdminDashboardTest extends TestCase
{
    use DatabaseTransactions;

    public function test_dashboard_page_can_be_rendered_with_live_data(): void
    {
        $this->actingAs(User::factory()->create());

        $provider = PaymentProvider::query()->updateOrCreate(
            ['code' => 'tripay'],
            ['name' => 'Tripay', 'is_active' => true],
        );

        $application = Application::factory()->create([
            'code' => 'BLASKU',
            'name' => 'Blasku',
            'default_provider' => $provider->code,
        ]);

        $payment = PaymentOrder::factory()->paid()->create([
            'public_id' => 'pay_test_001',
            'application_id' => $application->id,
            'provider_code' => $provider->code,
            'status' => PaymentOrderStatus::Paid,
        ]);

        ProviderTransaction::factory()->create([
            'payment_order_id' => $payment->id,
            'provider' => $provider->code,
            'merchant_ref' => $payment->merchant_ref,
        ]);

        PaymentEvent::factory()->create([
            'payment_order_id' => $payment->id,
            'event_type' => 'payment.paid',
        ]);

        WebhookDelivery::factory()->create([
            'payment_order_id' => $payment->id,
            'application_id' => $application->id,
            'status' => WebhookDeliveryStatus::Success,
        ]);

        $response = $this->get('/admin/dashboard');

        $response->assertOk();
        $response->assertSee('Payment Hub Dashboard');
        $response->assertSee('pay_test_001');
        $response->assertSee('Tripay');
    }
}
