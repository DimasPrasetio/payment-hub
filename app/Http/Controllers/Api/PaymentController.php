<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Api\CreatePaymentRequest;
use App\Http\Requests\Api\PaymentListRequest;
use App\Http\Requests\Api\PaymentLookupRequest;
use App\Http\Requests\Api\RefundPaymentRequest;
use App\Models\PaymentOrder;
use App\Services\ApiResponse;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentController extends ApiController
{
    public function __construct(private readonly PaymentService $paymentService)
    {
    }

    public function index(PaymentListRequest $request): JsonResponse
    {
        $application = $this->clientApplication($request);
        $filters = $request->validated();

        $payments = PaymentOrder::query()
            ->with(['application:id,code', 'latestProviderTransaction'])
            ->where('application_id', $application->id)
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['provider_code'] ?? null, fn ($query, $providerCode) => $query->where('provider_code', $providerCode))
            ->when($filters['payment_method'] ?? null, fn ($query, $paymentMethod) => $query->where('payment_method', $paymentMethod))
            ->when($filters['date_from'] ?? null, fn ($query, $dateFrom) => $query->whereDate('created_at', '>=', $dateFrom))
            ->when($filters['date_to'] ?? null, fn ($query, $dateTo) => $query->whereDate('created_at', '<=', $dateTo))
            ->orderBy($filters['sort_by'] ?? 'created_at', $filters['sort_order'] ?? 'desc')
            ->paginate($filters['per_page'] ?? 20)
            ->withQueryString();

        return ApiResponse::paginated(
            $payments,
            collect($payments->items())->map(fn (PaymentOrder $payment) => $this->paymentSummaryPayload($payment))->all(),
        );
    }

    public function store(CreatePaymentRequest $request): JsonResponse
    {
        $result = $this->paymentService->create($this->clientApplication($request), $request->validated());

        return ApiResponse::success(
            $this->createdPaymentPayload($result['payment']),
            $result['http_status'],
        );
    }

    public function show(Request $request, string $paymentId): JsonResponse
    {
        $payment = $this->findPaymentForApplication($paymentId, $this->clientApplication($request));

        return ApiResponse::success($this->paymentDetailPayload($payment));
    }

    public function lookup(PaymentLookupRequest $request): JsonResponse
    {
        $application = $this->clientApplication($request);
        $filters = $request->validated();

        $payment = PaymentOrder::query()
            ->with(['application:id,code', 'latestProviderTransaction'])
            ->where('application_id', $application->id)
            ->where('external_order_id', $filters['external_order_id'])
            ->first();

        if (! $payment) {
            throw new \App\Exceptions\ApiException(
                'PAYMENT_NOT_FOUND',
                'Payment not found.',
                404,
            );
        }

        return ApiResponse::success($this->paymentDetailPayload($payment));
    }

    public function cancel(Request $request, string $paymentId): JsonResponse
    {
        $payment = $this->findPaymentForApplication($paymentId, $this->clientApplication($request));
        $payment = $this->paymentService->cancel($payment);

        return ApiResponse::success([
            'payment_id' => $payment->public_id,
            'status' => $payment->status->value,
            'cancelled_at' => now()->toIso8601String(),
        ]);
    }

    public function refund(RefundPaymentRequest $request, string $paymentId): JsonResponse
    {
        $payment = $this->findPaymentForApplication($paymentId, $this->clientApplication($request));
        $payment = $this->paymentService->refund(
            $payment,
            $request->integer('amount'),
            (string) $request->string('reason'),
        );

        return ApiResponse::success([
            'payment_id' => $payment->public_id,
            'refund_amount' => $request->integer('amount'),
            'status' => $payment->status->value,
            'refund_method' => 'api',
            'refunded_at' => now()->toIso8601String(),
        ]);
    }

    public function sync(Request $request, string $paymentId): JsonResponse
    {
        $payment = $this->findPaymentForApplication($paymentId, $this->clientApplication($request));
        $result = $this->paymentService->syncStatus($payment);

        return ApiResponse::success(array_merge(
            $this->paymentDetailPayload($result['payment']),
            [
                'sync' => [
                    'status_changed' => (bool) $result['status_changed'],
                    'provider_status' => $result['provider_status'],
                    'event_type' => $result['event_type'],
                    'synced_at' => $result['synced_at'],
                    'source' => 'provider_query',
                ],
            ],
        ));
    }
}
