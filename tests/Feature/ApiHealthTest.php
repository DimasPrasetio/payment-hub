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
        $response->assertJsonPath('version', config('versioning.release'));
        $response->assertJsonPath('api_version', config('versioning.api.current'));
        $response->assertJsonPath('services.database', 'connected');
        $response->assertJsonPath('services.queue', 'sync');
        $response->assertJsonStructure([
            'status',
            'version',
            'api_version',
            'timestamp',
            'services' => ['database', 'redis', 'queue'],
        ]);
    }
}
