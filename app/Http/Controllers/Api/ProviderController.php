<?php

namespace App\Http\Controllers\Api;

use App\Models\PaymentProvider;
use App\Services\ApiResponse;
use Illuminate\Http\JsonResponse;

class ProviderController extends ApiController
{
    public function index(): JsonResponse
    {
        $providers = PaymentProvider::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get([
                'code',
                'name',
                'sandbox_mode',
            ])
            ->map(fn (PaymentProvider $provider) => [
                'code' => $provider->code,
                'name' => $provider->name,
                'sandbox_mode' => (bool) $provider->sandbox_mode,
            ])
            ->all();

        return ApiResponse::success($providers);
    }
}
