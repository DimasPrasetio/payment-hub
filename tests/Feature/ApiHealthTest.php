<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class ApiHealthTest extends TestCase
{
    use DatabaseTransactions;

    public function test_health_endpoint_returns_contract_shape(): void
    {
        $response = $this->getJson('/api/v1/health');

        $response->assertOk();
        $response->assertJsonPath('status', 'healthy');
        $response->assertJsonPath('version', '1.0.0');
        $response->assertJsonPath('services.database', 'connected');
        $response->assertJsonPath('services.queue', 'sync');
        $response->assertJsonStructure([
            'status',
            'version',
            'timestamp',
            'services' => ['database', 'redis', 'queue'],
        ]);
    }
}
