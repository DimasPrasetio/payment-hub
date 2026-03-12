<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\ApiException;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class ProviderCallbackController extends ApiController
{
    public function __construct(private readonly PaymentService $paymentService) {}

    public function store(Request $request, string $providerCode): JsonResponse
    {
        try {
            $this->paymentService->handleProviderCallback(strtolower($providerCode), $request);

            return response()->json([
                'success' => true,
            ]);
        } catch (ApiException $exception) {
            return $this->errorResponse(
                $exception->errorCode(),
                $exception->getMessage(),
                $exception->status(),
            );
        } catch (Throwable $exception) {
            report($exception);

            return $this->errorResponse(
                'INTERNAL_ERROR',
                'An unexpected internal error occurred while processing the callback.',
                500,
            );
        }
    }

    protected function errorResponse(string $code, string $message, int $status): JsonResponse
    {
        return response()->json([
            'success' => false,
            'error' => [
                'code' => $code,
                'message' => $message,
            ],
        ], $status);
    }
}
