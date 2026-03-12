<?php

namespace App\Providers\Tripay;

use App\Contracts\PaymentProviderInterface;
use App\Enums\PaymentOrderStatus;
use App\Exceptions\ApiException;
use App\Models\PaymentMethodMapping;
use App\Models\PaymentOrder;
use App\Models\PaymentProvider;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class TripayProvider implements PaymentProviderInterface
{
    public function createTransaction(PaymentOrder $payment, PaymentMethodMapping $mapping, PaymentProvider $provider): array
    {
        $payload = $this->buildTransactionPayload($payment, $mapping, $provider);

        if (! $this->hasApiCredentials($provider)) {
            throw $this->providerConfigException($provider);
        }

        try {
            $response = Http::acceptJson()
                ->asForm()
                ->timeout(15)
                ->withToken((string) data_get($provider->config, 'api_key'))
                ->post($this->apiBaseUrl($provider).'/transaction/create', $payload);
        } catch (ConnectionException $exception) {
            throw new ApiException(
                'PROVIDER_TIMEOUT',
                'Payment provider timed out.',
                504,
                [
                    'provider' => $provider->code,
                ],
            );
        }

        if ($response->failed()) {
            throw new ApiException(
                'PROVIDER_ERROR',
                'Payment provider returned an error.',
                502,
                [
                    'provider' => $provider->code,
                    'provider_message' => $response->json('message') ?? $response->body(),
                ],
            );
        }

        $data = $response->json('data', []);

        return [
            'provider_reference' => data_get($data, 'reference'),
            'payment_method' => data_get($data, 'payment_method') ?: $mapping->provider_method_code,
            'payment_url' => data_get($data, 'checkout_url'),
            'pay_code' => data_get($data, 'pay_code'),
            'qr_string' => data_get($data, 'qr_string'),
            'qr_url' => data_get($data, 'qr_url'),
            'raw_request' => $payload,
            'raw_response' => $response->json() ?? ['body' => $response->body()],
        ];
    }

    public function queryTransaction(PaymentOrder $payment, PaymentProvider $provider): array
    {
        if (! $this->hasApiCredentials($provider)) {
            throw $this->providerConfigException($provider);
        }

        $reference = (string) optional($payment->latestProviderTransaction)->provider_reference;

        if ($reference === '') {
            return [
                'provider' => $provider->code,
                'merchant_ref' => $payment->merchant_ref,
                'status' => 'UNPAID',
                'provider_status' => 'UNPAID',
                'internal_status' => PaymentOrderStatus::Pending,
                'amount' => (int) $payment->amount,
                'provider_reference' => null,
                'paid_at' => null,
                'payload' => [],
            ];
        }

        try {
            $response = Http::acceptJson()
                ->timeout(15)
                ->withToken((string) data_get($provider->config, 'api_key'))
                ->get($this->apiBaseUrl($provider).'/transaction/detail', [
                    'reference' => $reference,
                ]);
        } catch (ConnectionException $exception) {
            throw new ApiException(
                'PROVIDER_TIMEOUT',
                'Payment provider timed out.',
                504,
                [
                    'provider' => $provider->code,
                ],
            );
        }

        if ($response->failed()) {
            throw new ApiException(
                'PROVIDER_ERROR',
                'Payment provider returned an error.',
                502,
                [
                    'provider' => $provider->code,
                    'provider_message' => $response->json('message') ?? $response->body(),
                ],
            );
        }

        $data = $response->json('data', []);
        $providerStatus = strtoupper((string) data_get($data, 'status', 'UNPAID'));

        return [
            'provider' => $provider->code,
            'merchant_ref' => $payment->merchant_ref,
            'status' => $providerStatus,
            'provider_status' => $providerStatus,
            'internal_status' => $this->mapStatus($providerStatus),
            'amount' => (int) data_get($data, 'amount', $payment->amount),
            'provider_reference' => (string) data_get($data, 'reference', $reference),
            'paid_at' => $this->parsePaidAt(data_get($data, 'paid_at')),
            'payload' => $response->json() ?? ['body' => $response->body()],
        ];
    }

    public function verifyCallback(Request $request, PaymentProvider $provider): array
    {
        $rawBody = (string) $request->getContent();
        $payload = $rawBody !== ''
            ? (json_decode($rawBody, true) ?: [])
            : $request->all();
        $signature = trim((string) $request->headers->get('X-Callback-Signature', ''));
        $privateKey = (string) data_get($provider->config, 'private_key', '');
        $expectedSignature = $privateKey !== '' ? hash_hmac('sha256', $rawBody, $privateKey) : '';
        $providerStatus = strtoupper((string) data_get($payload, 'status', ''));
        $paidAt = data_get($payload, 'paid_at');

        return [
            'is_valid' => $signature !== '' && $expectedSignature !== '' && hash_equals($expectedSignature, $signature),
            'payload' => $payload,
            'provider_status' => $providerStatus,
            'internal_status' => $this->mapStatus($providerStatus),
            'merchant_ref' => (string) data_get($payload, 'merchant_ref', ''),
            'amount' => (int) data_get($payload, 'total_amount', 0),
            'provider_reference' => (string) data_get($payload, 'reference', ''),
            'paid_at' => $this->parsePaidAt($paidAt),
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
        throw new ApiException(
            'REFUND_NOT_SUPPORTED',
            sprintf("Provider '%s' does not support refund via API in the current implementation. Please process manually.", $payment->provider_code),
            422,
        );
    }

    protected function mapStatus(string $providerStatus): PaymentOrderStatus
    {
        return match ($providerStatus) {
            'PAID' => PaymentOrderStatus::Paid,
            'FAILED' => PaymentOrderStatus::Failed,
            'EXPIRED' => PaymentOrderStatus::Expired,
            'REFUND', 'REFUNDED' => PaymentOrderStatus::Refunded,
            'UNPAID' => PaymentOrderStatus::Pending,
            default => PaymentOrderStatus::Pending,
        };
    }

    protected function parsePaidAt(mixed $paidAt): ?CarbonImmutable
    {
        if ($paidAt === null || $paidAt === '') {
            return null;
        }

        if (is_numeric($paidAt)) {
            return CarbonImmutable::createFromTimestamp((int) $paidAt);
        }

        return CarbonImmutable::parse((string) $paidAt);
    }

    protected function payCode(): string
    {
        return (string) random_int(1000000000, 9999999999);
    }

    protected function qrisString(PaymentOrder $payment): string
    {
        return sprintf(
            'PAYMENTHUB|%s|%s|%s|%s',
            $payment->merchant_ref,
            $payment->amount,
            $payment->currency,
            $payment->expires_at?->timestamp ?? now()->addHour()->timestamp,
        );
    }

    protected function buildTransactionPayload(PaymentOrder $payment, PaymentMethodMapping $mapping, PaymentProvider $provider): array
    {
        $merchantCode = (string) data_get($provider->config, 'merchant_code', 'TRIPAY');
        $privateKey = (string) data_get($provider->config, 'private_key', '');

        return array_filter([
            'method' => $mapping->provider_method_code,
            'merchant_ref' => $payment->merchant_ref,
            'amount' => $payment->amount,
            'customer_name' => $payment->customer_name,
            'customer_email' => $payment->customer_email,
            'customer_phone' => $payment->customer_phone,
            'order_items' => [[
                'sku' => $payment->external_order_id,
                'name' => (string) data_get($payment->metadata, 'product_name', $payment->external_order_id),
                'price' => $payment->amount,
                'quantity' => 1,
            ]],
            'return_url' => data_get($provider->config, 'return_url'),
            'expired_time' => $payment->expires_at?->timestamp,
            'signature' => $privateKey !== ''
                ? hash_hmac('sha256', $merchantCode.$payment->merchant_ref.$payment->amount, $privateKey)
                : null,
        ], static fn ($value) => $value !== null && $value !== '');
    }

    protected function createStubTransaction(
        PaymentOrder $payment,
        PaymentMethodMapping $mapping,
        PaymentProvider $provider,
        array $payload
    ): array {
        $providerReference = strtoupper((string) data_get($provider->config, 'merchant_code', 'TRIPAY'))
            .'_'
            .strtoupper(Str::random(12));
        $baseUrl = $this->publicBaseUrl($provider);
        $isBankTransfer = str_contains($mapping->internal_code, 'BANK_TRANSFER') || $mapping->group === 'bank_transfer';
        $isQris = $mapping->internal_code === 'QRIS' || $mapping->provider_method_code === 'QRIS';

        return [
            'provider_reference' => $providerReference,
            'payment_method' => $mapping->provider_method_code,
            'payment_url' => $baseUrl.'/checkout/'.$providerReference,
            'pay_code' => $isBankTransfer ? $this->payCode() : null,
            'qr_string' => $isQris ? $this->qrisString($payment) : null,
            'qr_url' => $isQris ? $baseUrl.'/qr/'.$providerReference : null,
            'raw_request' => $payload,
            'raw_response' => [
                'status' => 'accepted',
                'mode' => 'adapter_stub',
                'expires_at' => $payment->expires_at?->toIso8601String(),
            ],
        ];
    }

    protected function hasApiCredentials(PaymentProvider $provider): bool
    {
        return (string) data_get($provider->config, 'api_key', '') !== ''
            && (string) data_get($provider->config, 'merchant_code', '') !== ''
            && (string) data_get($provider->config, 'private_key', '') !== '';
    }

    protected function providerConfigException(PaymentProvider $provider): ApiException
    {
        return new ApiException(
            'PROVIDER_CONFIG_INCOMPLETE',
            sprintf("Provider '%s' credentials are incomplete.", $provider->code),
            422,
        );
    }

    protected function apiBaseUrl(PaymentProvider $provider): string
    {
        $baseUrl = rtrim((string) data_get($provider->config, 'api_base_url', data_get($provider->config, 'base_url', '')), '/');

        if ($baseUrl === '') {
            return $provider->sandbox_mode
                ? 'https://tripay.co.id/api-sandbox'
                : 'https://tripay.co.id/api';
        }

        if (preg_match('#/api(?:-sandbox)?$#', $baseUrl) === 1) {
            return $baseUrl;
        }

        return $baseUrl.($provider->sandbox_mode ? '/api-sandbox' : '/api');
    }

    protected function publicBaseUrl(PaymentProvider $provider): string
    {
        $baseUrl = rtrim((string) data_get($provider->config, 'public_base_url', data_get($provider->config, 'base_url', 'https://tripay.co.id')), '/');

        return preg_replace('#/api(?:-sandbox)?$#', '', $baseUrl) ?: 'https://tripay.co.id';
    }
}
