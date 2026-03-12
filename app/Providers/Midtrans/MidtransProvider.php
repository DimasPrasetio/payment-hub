<?php

namespace App\Providers\Midtrans;

use App\Contracts\PaymentProviderInterface;
use App\Enums\PaymentOrderStatus;
use App\Exceptions\ApiException;
use App\Models\PaymentMethodMapping;
use App\Models\PaymentOrder;
use App\Models\PaymentProvider;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Throwable;

class MidtransProvider implements PaymentProviderInterface
{
    public function __construct(
        private readonly MidtransClient $client,
    ) {}

    public function createTransaction(PaymentOrder $payment, PaymentMethodMapping $mapping, PaymentProvider $provider): array
    {
        $payload = $this->buildTransactionPayload($payment, $mapping, $provider);

        if (! $this->hasApiCredentials($provider)) {
            throw new ApiException(
                'PROVIDER_CONFIG_INCOMPLETE',
                'Midtrans provider credentials are incomplete.',
                422,
            );
        }

        try {
            $response = $this->client->createTransaction(
                $provider,
                $payload,
                $payment->idempotency_key,
                data_get($provider->config, 'notification_url', route('api.callbacks.store', ['provider_code' => $provider->code])),
            );
        } catch (Throwable $exception) {
            throw $this->providerException($provider, $exception);
        }

        $data = $this->normalizeSdkPayload($response);

        return [
            'provider_reference' => (string) ($data['token'] ?? $data['redirect_url'] ?? strtoupper('MID_'.Str::random(12))),
            'payment_method' => $mapping->provider_method_code,
            'payment_url' => $data['redirect_url'] ?? null,
            'pay_code' => null,
            'qr_string' => null,
            'qr_url' => null,
            'raw_request' => $payload,
            'raw_response' => $data,
        ];
    }

    public function queryTransaction(PaymentOrder $payment, PaymentProvider $provider): array
    {
        if (! $this->hasApiCredentials($provider)) {
            throw new ApiException(
                'PROVIDER_CONFIG_INCOMPLETE',
                'Midtrans provider credentials are incomplete.',
                422,
            );
        }

        try {
            $response = $this->client->queryTransaction($provider, $payment->merchant_ref);
        } catch (Throwable $exception) {
            throw $this->providerException($provider, $exception);
        }

        $data = $this->normalizeSdkPayload($response);
        $providerStatus = strtolower((string) ($data['transaction_status'] ?? 'pending'));
        $paidAt = $this->parsePaidAt(
            $data['settlement_time'] ?? $data['transaction_time'] ?? $data['transaction_timestamp'] ?? null
        );

        return [
            'provider' => $provider->code,
            'merchant_ref' => $payment->merchant_ref,
            'status' => strtoupper($providerStatus),
            'provider_status' => strtoupper($providerStatus),
            'internal_status' => $this->mapStatus($providerStatus, strtolower((string) ($data['fraud_status'] ?? ''))),
            'amount' => (int) round((float) ($data['gross_amount'] ?? $payment->amount)),
            'provider_reference' => $data['transaction_id'] ?? null,
            'paid_at' => $paidAt,
            'payload' => $data,
        ];
    }

    public function verifyCallback(Request $request, PaymentProvider $provider): array
    {
        $rawBody = (string) $request->getContent();
        $payload = $rawBody !== ''
            ? (json_decode($rawBody, true) ?: [])
            : $request->all();
        $signature = trim((string) ($request->headers->get('X-Callback-Signature') ?: data_get($payload, 'signature_key', '')));
        $expectedSignature = $this->callbackSignature($payload, $provider);
        $transactionStatus = strtolower((string) data_get($payload, 'transaction_status', 'pending'));
        $fraudStatus = strtolower((string) data_get($payload, 'fraud_status', ''));

        return [
            'is_valid' => $signature !== '' && $expectedSignature !== '' && hash_equals($expectedSignature, $signature),
            'payload' => $payload,
            'provider_status' => strtoupper($transactionStatus),
            'internal_status' => $this->mapStatus($transactionStatus, $fraudStatus),
            'merchant_ref' => (string) data_get($payload, 'order_id', ''),
            'amount' => (int) round((float) data_get($payload, 'gross_amount', 0)),
            'provider_reference' => (string) data_get($payload, 'transaction_id', ''),
            'paid_at' => $this->parsePaidAt(
                data_get($payload, 'settlement_time')
                    ?: data_get($payload, 'transaction_time')
                    ?: data_get($payload, 'transaction_timestamp')
            ),
        ];
    }

    public function getAvailablePaymentMethods(PaymentProvider $provider): array
    {
        return $provider->paymentMethodMappings()
            ->active()
            ->orderBy('display_name')
            ->get()
            ->map(fn (PaymentMethodMapping $mapping) => [
                'code' => $mapping->internal_code,
                'provider_method_code' => $mapping->provider_method_code,
                'display_name' => $mapping->display_name,
                'group' => $mapping->group,
            ])
            ->all();
    }

    public function refund(PaymentOrder $payment, int $amount, string $reason, PaymentProvider $provider): array
    {
        if (! (bool) data_get($provider->config, 'supports_refund_api', false)) {
            throw new ApiException(
                'REFUND_NOT_SUPPORTED',
                sprintf("Provider '%s' does not support refund via API. Please process manually.", $payment->provider_code),
                422,
            );
        }

        if (! $this->hasApiCredentials($provider)) {
            throw new ApiException(
                'PROVIDER_CONFIG_INCOMPLETE',
                'Midtrans provider credentials are incomplete.',
                422,
            );
        }

        $refundReference = 'RFND_'.strtoupper(Str::random(12));

        try {
            $response = $this->client->refund($provider, $payment->merchant_ref, [
                'refund_key' => $refundReference,
                'amount' => $amount,
                'reason' => $reason,
            ]);
        } catch (Throwable $exception) {
            throw $this->providerException($provider, $exception);
        }

        $data = $this->normalizeSdkPayload($response);

        return [
            'refund_reference' => (string) ($data['refund_key'] ?? $refundReference),
            'refund_amount' => $amount,
            'refund_method' => 'api',
            'reason' => $reason,
            'refunded_at' => now()->toIso8601String(),
            'raw_response' => $data,
        ];
    }

    protected function mapStatus(string $transactionStatus, ?string $fraudStatus = null): PaymentOrderStatus
    {
        return match ($transactionStatus) {
            'settlement' => PaymentOrderStatus::Paid,
            'capture' => $fraudStatus === 'challenge' ? PaymentOrderStatus::Pending : PaymentOrderStatus::Paid,
            'expire' => PaymentOrderStatus::Expired,
            'deny', 'cancel', 'failure' => PaymentOrderStatus::Failed,
            'refund', 'chargeback' => PaymentOrderStatus::Refunded,
            'partial_refund', 'partial_chargeback' => PaymentOrderStatus::Paid,
            'pending', 'authorize' => PaymentOrderStatus::Pending,
            default => PaymentOrderStatus::Pending,
        };
    }

    protected function parsePaidAt(mixed $paidAt): ?CarbonImmutable
    {
        if ($paidAt === null || $paidAt === '') {
            return null;
        }

        return CarbonImmutable::parse((string) $paidAt);
    }

    protected function buildTransactionPayload(PaymentOrder $payment, PaymentMethodMapping $mapping, PaymentProvider $provider): array
    {
        return array_filter([
            'transaction_details' => [
                'order_id' => $payment->merchant_ref,
                'gross_amount' => $payment->amount,
            ],
            'enabled_payments' => [$mapping->provider_method_code],
            'customer_details' => [
                'first_name' => $payment->customer_name,
                'email' => $payment->customer_email,
                'phone' => $payment->customer_phone,
            ],
            'item_details' => [[
                'id' => $payment->external_order_id,
                'price' => $payment->amount,
                'quantity' => 1,
                'name' => (string) data_get($payment->metadata, 'product_name', $payment->external_order_id),
            ]],
            'callbacks' => array_filter([
                'finish' => data_get($provider->config, 'return_url'),
            ]),
            'expiry' => [
                'start_time' => now()->format('Y-m-d H:i:s O'),
                'unit' => 'minutes',
                'duration' => max(1, now()->diffInMinutes($payment->expires_at ?? now()->addHour())),
            ],
            'custom_field1' => $payment->external_order_id,
        ], static fn ($value) => $value !== null && $value !== '' && $value !== []);
    }

    protected function createStubTransaction(
        PaymentOrder $payment,
        PaymentMethodMapping $mapping,
        PaymentProvider $provider,
        array $payload
    ): array {
        $providerReference = 'MIDTOKEN_'.strtoupper(Str::random(12));
        $baseUrl = $provider->sandbox_mode ? 'https://app.sandbox.midtrans.com' : 'https://app.midtrans.com';

        return [
            'provider_reference' => $providerReference,
            'payment_method' => $mapping->provider_method_code,
            'payment_url' => $baseUrl.'/snap/v2/vtweb/'.$providerReference,
            'pay_code' => null,
            'qr_string' => null,
            'qr_url' => null,
            'raw_request' => $payload,
            'raw_response' => [
                'status' => 'accepted',
                'mode' => 'adapter_stub',
                'token' => $providerReference,
                'redirect_url' => $baseUrl.'/snap/v2/vtweb/'.$providerReference,
            ],
        ];
    }

    protected function hasApiCredentials(PaymentProvider $provider): bool
    {
        return (string) data_get($provider->config, 'server_key', '') !== '';
    }

    protected function callbackSignature(array $payload, PaymentProvider $provider): string
    {
        $serverKey = (string) data_get($provider->config, 'server_key', '');
        $orderId = (string) data_get($payload, 'order_id', '');
        $statusCode = (string) data_get($payload, 'status_code', '');
        $grossAmount = (string) data_get($payload, 'gross_amount', '');

        if ($serverKey === '' || $orderId === '' || $statusCode === '' || $grossAmount === '') {
            return '';
        }

        return hash('sha512', $orderId.$statusCode.$grossAmount.$serverKey);
    }

    protected function normalizeSdkPayload(mixed $payload): array
    {
        if (is_array($payload)) {
            return $payload;
        }

        if (is_object($payload)) {
            return json_decode(json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), true) ?: [];
        }

        return [];
    }

    protected function providerException(PaymentProvider $provider, Throwable $exception): ApiException
    {
        $message = strtolower($exception->getMessage());

        if (str_contains($message, 'timed out')) {
            return new ApiException(
                'PROVIDER_TIMEOUT',
                'Payment provider timed out.',
                504,
                ['provider' => $provider->code],
            );
        }

        return new ApiException(
            'PROVIDER_ERROR',
            'Payment provider returned an error.',
            502,
            [
                'provider' => $provider->code,
                'provider_message' => $exception->getMessage(),
            ],
        );
    }
}
