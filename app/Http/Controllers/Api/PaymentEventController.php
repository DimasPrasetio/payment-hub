<?php

namespace App\Http\Controllers\Api;

use App\Models\PaymentEvent;
use App\Services\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentEventController extends ApiController
{
    public function index(Request $request, string $paymentId): JsonResponse
    {
        $payment = $this->findPaymentForApplication($paymentId, $this->clientApplication($request));

        $events = $payment->paymentEvents()
            ->orderBy('created_at')
            ->get()
            ->map(function (PaymentEvent $event) use ($payment) {
                return [
                    'id' => $event->public_id,
                    'payment_id' => $payment->public_id,
                    'event_type' => $event->event_type,
                    'payload' => $event->payload,
                    'created_at' => $event->created_at?->toIso8601String(),
                ];
            })
            ->all();

        return ApiResponse::success($events);
    }
}
