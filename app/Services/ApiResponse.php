<?php

namespace App\Services;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ApiResponse
{
    public static function success(mixed $data, int $status = 200, array $extra = []): JsonResponse
    {
        return self::response([
            'success' => true,
            'data' => $data,
            ...$extra,
            'meta' => self::meta(),
        ], $status);
    }

    public static function paginated(LengthAwarePaginator $paginator, mixed $data, int $status = 200): JsonResponse
    {
        return self::success($data, $status, [
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
                'has_more' => $paginator->hasMorePages(),
            ],
        ]);
    }

    public static function error(string $code, string $message, int $status, array $details = []): JsonResponse
    {
        $error = [
            'code' => $code,
            'message' => $message,
        ];

        if ($details !== []) {
            $error['details'] = $details;
        }

        return self::response([
            'success' => false,
            'error' => $error,
            'meta' => self::meta(),
        ], $status);
    }

    public static function meta(?Request $request = null): array
    {
        $request ??= request();
        $requestId = $request?->attributes->get('request_id');

        if (! is_string($requestId) || $requestId === '') {
            $requestId = 'req_' . Str::lower(Str::random(12));
            $request?->attributes->set('request_id', $requestId);
        }

        return [
            'timestamp' => now()->toIso8601String(),
            'request_id' => $requestId,
        ];
    }

    protected static function response(array $payload, int $status): JsonResponse
    {
        $requestId = data_get($payload, 'meta.request_id');

        return response()
            ->json($payload, $status)
            ->header('X-Request-Id', is_string($requestId) ? $requestId : self::meta()['request_id']);
    }
}
