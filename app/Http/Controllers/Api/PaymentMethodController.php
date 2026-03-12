<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Api\PaymentMethodListRequest;
use App\Models\PaymentMethodMapping;
use App\Services\ApiResponse;
use Illuminate\Http\JsonResponse;

class PaymentMethodController extends ApiController
{
    public function index(PaymentMethodListRequest $request): JsonResponse
    {
        $filters = $request->validated();

        $methods = PaymentMethodMapping::query()
            ->when($filters['active_only'] ?? true, fn ($query) => $query->where('is_active', true))
            ->whereHas('paymentProvider', fn ($query) => $query->where('is_active', true))
            ->when($filters['provider_code'] ?? null, fn ($query, $code) => $query->where('provider_code', $code))
            ->when($filters['group'] ?? null, fn ($query, $group) => $query->where('group', $group))
            ->when($filters['amount'] ?? null, function ($query, $amount) {
                $query
                    ->where(function ($amountQuery) use ($amount) {
                        $amountQuery->whereNull('min_amount')->orWhere('min_amount', '<=', $amount);
                    })
                    ->where(function ($amountQuery) use ($amount) {
                        $amountQuery->whereNull('max_amount')->orWhere('max_amount', '>=', $amount);
                    });
            })
            ->orderBy('provider_code')
            ->orderBy('display_name')
            ->get();

        $data = $methods->map(function (PaymentMethodMapping $method) {
            return [
                'code' => $method->internal_code,
                'display_name' => $method->display_name,
                'group' => $method->group,
                'provider' => $method->provider_code,
                'provider_method_code' => $method->provider_method_code,
                'icon_url' => $method->icon_url,
                'fee_flat' => (int) $method->fee_flat,
                'fee_percent' => (float) $method->fee_percent,
                'min_amount' => $method->min_amount,
                'max_amount' => $method->max_amount,
                'is_active' => (bool) $method->is_active,
            ];
        })->all();

        return ApiResponse::success($data);
    }
}
