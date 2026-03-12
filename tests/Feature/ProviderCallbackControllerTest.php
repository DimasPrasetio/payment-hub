<?php

namespace Tests\Feature;

use App\Services\PaymentService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use RuntimeException;
use Tests\TestCase;

class ProviderCallbackControllerTest extends TestCase
{
    use DatabaseTransactions;

    public function test_callback_returns_server_error_when_internal_processing_fails(): void
    {
        $this->mock(PaymentService::class, function ($mock) {
            $mock->shouldReceive('handleProviderCallback')
                ->once()
                ->with('tripay', \Mockery::type(Request::class))
                ->andThrow(new RuntimeException('boom'));
        });

        $response = $this->postJson('/api/v1/callback/tripay', [
            'merchant_ref' => 'BLASKU-20260312-ABC123',
            'status' => 'PAID',
        ]);

        $response->assertStatus(500);
        $response->assertExactJson([
            'success' => false,
            'error' => [
                'code' => 'INTERNAL_ERROR',
                'message' => 'An unexpected internal error occurred while processing the callback.',
            ],
        ]);
    }
}
