<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Schema;
use Throwable;

class HealthController extends ApiController
{
    public function __invoke(): JsonResponse
    {
        try {
            DB::connection()->getPdo();

            return response()->json([
                'status' => 'healthy',
                'version' => '1.0.0',
                'timestamp' => now()->toIso8601String(),
                'services' => [
                    'database' => 'connected',
                    'redis' => $this->redisStatus(),
                    'queue' => 'running',
                    'payment_orders_table' => Schema::hasTable('payment_orders'),
                ],
            ]);
        } catch (Throwable $exception) {
            return response()->json([
                'status' => 'degraded',
                'version' => '1.0.0',
                'timestamp' => now()->toIso8601String(),
                'services' => [
                    'database' => 'disconnected',
                ],
                'message' => 'Database connection is unavailable.',
            ], 503);
        }
    }

    protected function redisStatus(): string
    {
        try {
            if (config('database.redis.client') === null) {
                return 'not_configured';
            }

            Redis::connection()->client();

            return 'connected';
        } catch (Throwable $exception) {
            return 'disconnected';
        }
    }
}
