<?php

namespace App\Providers\Xendit;

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

class XenditProvider implements PaymentProviderInterface
{
    public function __construct(
        private readonly XenditClient $client,
    ) {
    }

    public function createTransaction(PaymentOrder $payment, PaymentMethodMapping $mapping, PaymentProvider $provider): array
    {
        $payload = $this->buildInvoicePayload($payment, $mapping, $provider);

        if (! $this->hasApiCredentials($provider)) {
            return $this->createStubTransaction($payment, $mapping, $provider, $payload);
        }

        try {
            $response = $this->client->createInvoice($provider, $payload);
        } catch (Throwable $exception) {
            throw $this->providerException($provider, $exception);
        }

        $data = $this->normalizeSdkPayload($response);

        return [
            'provider_reference' => (string) ($data['id'] ?? strtoupper('XND_' . Str::random(12))),
            'payment_method' => $mapping->provider_method_code,
            'payment_url' => $data['invoice_url'] ?? null,
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
            return [
                'provider' => $provider->code,
                'merchant_ref' => $payment->merchant_ref,
                'status' => 'PENDING',
                'provider_status' => 'PENDING',
                'internal_status' => PaymentOrderStatus::Pending,
                'amount' => (int) $payment->amount,
                'provider_reference' => optional($payment->latestProviderTransaction)->provider_reference,
                'paid_at' => null,
                'payload' => [],
            ];
        }

        try {
            $invoices = $this->client->getInvoicesByExternalId($provider, $payment->merchant_ref);
        } catch (Throwable $exception) {
            throw $this->providerException($provider, $exception);
        }

        $invoice = collect($invoices)->map(fn ($item) => $this->normalizeSdkPayload($item))->first();
        $providerStatus = strtoupper((string) ($invoice['status'] ?? 'PENDING'));

        return [
            'provider' => $provider->code,
            'merchant_ref' => $payment->merchant_ref,
            'status' => $providerStatus,
            'provider_status' => $providerStatus,
            'internal_status' => $this->mapStatus($providerStatus),
            'amount' => (int) round((float) ($invoice['amount'] ?? $payment->amount)),
            'provider_reference' => $invoice['id'] ?? null,
            'paid_at' => $this->parsePaidAt($invoice['paid_at'] ?? null),
            'payload' => $invoice ?? [],
        ];
    }

    public function verifyCallback(Request $request, PaymentProvider $provider): array
    {
        $rawBody = (string) $request->getContent();
        $payload = $rawBody !== ''
            ? (json_decode($rawBody, true) ?: [])
            : $request->all();
        $callbackToken = trim((string) $request->headers->get('x-callback-token', ''));
        $expectedToken = trim((string) data_get($provider->config, 'callback_token', ''));
        $providerStatus = strtoupper((string) data_get($payload, 'status', 'PENDING'));

        return [
            'is_valid' => $callbackToken !== '' && $expectedToken !== '' && hash_equals($expectedToken, $callbackToken),
            'payload' => $payload,
            'provider_status' => $providerStatus,
            'internal_status' => $this->mapStatus($providerStatus),
            'merchant_ref' => (string) data_get($payload, 'external_id', ''),
            'amount' => (int) round((float) data_get($payload, 'amount', 0)),
            'provider_reference' => (string) (data_get($payload, 'payment_id') ?: data_get($payload, 'id', '')),
            'paid_at' => $this->parsePaidAt(data_get($payload, 'paid_at')),
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
                'Xendit provider credentials are incomplete.',
                422,
            );
        }

        $providerReference = (string) optional($payment->latestProviderTransaction)->provider_reference;

        if ($providerReference === '') {
            throw new ApiException(
                'PROVIDER_REFERENCE_MISSING',
                'Provider reference is required to issue a refund.',
                422,
            );
        }

        $refundReference = 'RFND_' . strtolower(Str::uuid()->toString());

        try {
            $response = $this->client->createRefund($provider, $refundReference, [
                'invoice_id' => $providerReference,
                'reference_id' => $refundReference,
                'amount' => $amount,
                'currency' => $payment->currency,
                'reason' => $reason,
                'metadata' => [
                    'merchant_ref' => $payment->merchant_ref,
                ],
            ]);
        } catch (Throwable $exception) {
            throw $this->providerException($provider, $exception);
        }

        $data = $this->normalizeSdkPayload($response);

        return [
            'refund_reference' => (string) ($data['reference_id'] ?? $refundReference),
            'refund_amount' => $amount,
            'refund_method' => 'api',
            'reason' => $reason,
            'refunded_at' => now()->toIso8601String(),
            'raw_response' => $data,
        ];
    }

    protected function mapStatus(string $providerStatus): PaymentOrderStatus
    {
        return match ($providerStatus) {
            'PAID', 'SETTLED' => PaymentOrderStatus::Paid,
            'EXPIRED' => PaymentOrderStatus::Expired,
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

    protected function buildInvoicePayload(PaymentOrder $payment, PaymentMethodMapping $mapping, PaymentProvider $provider): array
    {
        $duration = max(1, now()->diffInSeconds($payment->expires_at ?? now()->addHour()));

        return array_filter([
            'external_id' => $payment->merchant_ref,
            'amount' => $payment->amount,
            'payer_email' => $payment->customer_email,
            'description' => (string) data_get($payment->metadata, 'product_name', $payment->external_order_id),
            'invoice_duration' => $duration,
            'currency' => $payment->currency,
            'success_redirect_url' => data_get($provider->config, 'return_url'),
            'failure_redirect_url' => data_get($provider->config, 'return_url'),
            'payment_methods' => [$mapping->provider_method_code],
            'customer' => [
                'given_names' => $payment->customer_name,
                'email' => $payment->customer_email,
                'mobile_number' => $payment->customer_phone,
            ],
            'metadata' => array_merge($payment->metadata ?? [], [
                'merchant_ref' => $payment->merchant_ref,
                'external_order_id' => $payment->external_order_id,
            ]),
        ], static fn ($value) => $value !== null && $value !== '' && $value !== []);
    }

    protected function createStubTransaction(
        PaymentOrder $payment,
        PaymentMethodMapping $mapping,
        PaymentProvider $provider,
        array $payload
    ): array {
        $providerReference = 'inv_' . strtolower(Str::random(18));
        $baseUrl = rtrim((string) data_get($provider->config, 'public_base_url', 'https://checkout.xendit.co'), '/');

        return [
            'provider_reference' => $providerReference,
            'payment_method' => $mapping->provider_method_code,
            'payment_url' => $baseUrl . '/web/' . $providerReference,
            'pay_code' => null,
            'qr_string' => null,
            'qr_url' => null,
            'raw_request' => $payload,
            'raw_response' => [
                'status' => 'PENDING',
                'mode' => 'adapter_stub',
                'id' => $providerReference,
                'invoice_url' => $baseUrl . '/web/' . $providerReference,
            ],
        ];
    }

    protected function hasApiCredentials(PaymentProvider $provider): bool
    {
        return (string) data_get($provider->config, 'secret_key', '') !== '';
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
