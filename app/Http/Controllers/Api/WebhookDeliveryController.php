<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\ApiException;
use App\Http\Requests\Api\WebhookDeliveryListRequest;
use App\Models\WebhookDelivery;
use App\Services\ApiResponse;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WebhookDeliveryController extends ApiController
{
    public function __construct(private readonly PaymentService $paymentService) {}

    public function index(WebhookDeliveryListRequest $request): JsonResponse
    {
        $application = $this->clientApplication($request);
        $filters = $request->validated();

        $deliveries = WebhookDelivery::query()
            ->with(['application:id,code', 'paymentOrder:id,public_id'])
            ->where('application_id', $application->id)
            ->when($filters['payment_id'] ?? null, function ($query, $paymentId) {
                $query->whereHas('paymentOrder', fn ($paymentQuery) => $paymentQuery->where('public_id', $paymentId));
            })
            ->when($filters['event_type'] ?? null, fn ($query, $eventType) => $query->where('event_type', $eventType))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->orderByDesc('created_at')
            ->paginate($filters['per_page'] ?? 20)
            ->withQueryString();

        $data = collect($deliveries->items())->map(function (WebhookDelivery $delivery) {
            return [
                'id' => $delivery->public_id,
                'payment_id' => $delivery->paymentOrder?->public_id,
                'application_code' => $delivery->application?->code,
                'event_type' => $delivery->event_type,
                'target_url' => $delivery->target_url,
                'status' => $delivery->status->value,
                'attempt' => $delivery->attempt,
                'response_code' => $delivery->response_code,
                'created_at' => $delivery->created_at?->toIso8601String(),
            ];
        })->all();

        return ApiResponse::paginated($deliveries, $data);
    }

    public function retry(Request $request, string $deliveryId): JsonResponse
    {
        $application = $this->clientApplication($request);

        $delivery = WebhookDelivery::query()
            ->where('application_id', $application->id)
            ->where('public_id', $deliveryId)
            ->first();

        if (! $delivery) {
            throw new ApiException(
                'NOT_FOUND',
                'Webhook delivery not found.',
                404,
            );
        }

        $delivery = $this->paymentService->retryWebhook($delivery);

        return ApiResponse::success([
            'id' => $delivery->public_id,
            'status' => $delivery->status->value,
            'attempt' => $delivery->attempt,
            'queued_at' => now()->toIso8601String(),
        ], 202);
    }
}
