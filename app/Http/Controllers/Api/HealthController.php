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
        $releaseVersion = (string) config('versioning.release');
        $apiVersion = (string) config('versioning.api.current', 'v1');

        try {
            DB::connection()->getPdo();

            return response()->json([
                'status' => 'healthy',
                'version' => $releaseVersion,
                'api_version' => $apiVersion,
                'timestamp' => now()->toIso8601String(),
                'services' => [
                    'database' => 'connected',
                    'redis' => $this->redisStatus(),
                    'queue' => $this->queueStatus(),
                    'payment_orders_table' => Schema::hasTable('payment_orders'),
                ],
            ]);
        } catch (Throwable $exception) {
            return response()->json([
                'status' => 'degraded',
                'version' => $releaseVersion,
                'api_version' => $apiVersion,
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

    protected function queueStatus(): string
    {
        $defaultConnection = config('queue.default');

        if (! is_string($defaultConnection) || $defaultConnection === '') {
            return 'not_configured';
        }

        $connection = config("queue.connections.{$defaultConnection}");
        $driver = is_array($connection) ? ($connection['driver'] ?? null) : null;

        return match ($driver) {
            'sync' => 'sync',
            'database' => Schema::hasTable((string) ($connection['table'] ?? 'jobs')) ? 'configured' : 'misconfigured',
            'redis' => $this->redisStatus() === 'connected' ? 'configured' : 'disconnected',
            'sqs', 'beanstalkd' => 'configured',
            default => 'not_configured',
        };
    }
}
