<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\PaymentOrder;
use Illuminate\Http\Request;

abstract class ApiController extends Controller
{
    protected function clientApplication(Request $request): Application
    {
        $application = $request->attributes->get('client_application');

        if (! $application instanceof Application) {
            throw new ApiException(
                'AUTHENTICATION_FAILED',
                'Application context is missing.',
                401,
            );
        }

        return $application;
    }

    protected function findPaymentForApplication(string $paymentId, Application $application): PaymentOrder
    {
        $payment = PaymentOrder::query()
            ->with(['application:id,code', 'latestProviderTransaction'])
            ->where('application_id', $application->id)
            ->where('public_id', $paymentId)
            ->first();

        if (! $payment) {
            throw new ApiException(
                'PAYMENT_NOT_FOUND',
                'Payment not found.',
                404,
            );
        }

        return $payment;
    }

    protected function createdPaymentPayload(PaymentOrder $payment): array
    {
        return array_merge($this->basePaymentPayload($payment), [
            'payment_instruction' => $this->paymentInstructionPayload($payment),
            'expires_at' => $payment->expires_at?->toIso8601String(),
            'created_at' => $payment->created_at?->toIso8601String(),
        ]);
    }

    protected function paymentDetailPayload(PaymentOrder $payment): array
    {
        return array_merge($this->basePaymentPayload($payment), [
            'payment_instruction' => $this->paymentInstructionPayload($payment),
            'metadata' => $payment->metadata,
            'paid_at' => $payment->paid_at?->toIso8601String(),
            'expires_at' => $payment->expires_at?->toIso8601String(),
            'created_at' => $payment->created_at?->toIso8601String(),
            'updated_at' => $payment->updated_at?->toIso8601String(),
        ]);
    }

    protected function paymentSummaryPayload(PaymentOrder $payment): array
    {
        return array_merge($this->basePaymentPayload($payment), [
            'paid_at' => $payment->paid_at?->toIso8601String(),
            'expires_at' => $payment->expires_at?->toIso8601String(),
            'created_at' => $payment->created_at?->toIso8601String(),
        ]);
    }

    protected function basePaymentPayload(PaymentOrder $payment): array
    {
        $payment->loadMissing(['application:id,code', 'latestProviderTransaction']);

        return [
            'payment_id' => $payment->public_id,
            'application_code' => $payment->application->code,
            'external_order_id' => $payment->external_order_id,
            'merchant_ref' => $payment->merchant_ref,
            'provider' => $payment->provider_code,
            'payment_method' => $payment->payment_method,
            'amount' => (int) $payment->amount,
            'currency' => $payment->currency,
            'status' => $payment->status->value,
            'customer' => [
                'name' => $payment->customer_name,
                'email' => $payment->customer_email,
                'phone' => $payment->customer_phone,
            ],
        ];
    }

    protected function paymentInstructionPayload(PaymentOrder $payment): array
    {
        $payment->loadMissing('latestProviderTransaction');
        $transaction = $payment->latestProviderTransaction;

        return [
            'payment_url' => $transaction?->payment_url,
            'pay_code' => $transaction?->pay_code,
            'qr_string' => $transaction?->qr_string,
            'qr_url' => $transaction?->qr_url,
        ];
    }
}
