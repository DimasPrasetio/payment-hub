<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\ApiException;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class ProviderCallbackController extends ApiController
{
    public function __construct(private readonly PaymentService $paymentService)
    {
    }

    public function store(Request $request, string $providerCode): JsonResponse
    {
        try {
            $this->paymentService->handleProviderCallback(strtolower($providerCode), $request);

            return response()->json([
                'success' => true,
            ]);
        } catch (ApiException $exception) {
            if (in_array($exception->errorCode(), ['INVALID_CALLBACK_SIGNATURE', 'PAYMENT_NOT_FOUND', 'PROVIDER_NOT_FOUND', 'PROVIDER_INACTIVE'], true)) {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => $exception->errorCode(),
                        'message' => $exception->getMessage(),
                    ],
                ], $exception->status());
            }

            report($exception);

            return response()->json([
                'success' => true,
            ]);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'success' => true,
            ]);
        }
    }
}
