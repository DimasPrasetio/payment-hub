<?php

namespace App\Services;

use App\Contracts\PaymentProviderInterface;
use App\Enums\PaymentOrderStatus;
use App\Enums\WebhookDeliveryStatus;
use App\Exceptions\ApiException;
use App\Models\Application;
use App\Models\PaymentEvent;
use App\Models\PaymentMethodMapping;
use App\Models\PaymentOrder;
use App\Models\PaymentProvider;
use App\Models\ProviderTransaction;
use App\Models\WebhookDelivery;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Database\QueryException;

class PaymentService
{
    public function __construct(
        private readonly ProviderResolver $providerResolver,
        private readonly WebhookService $webhookService,
    ) {}

    public function create(Application $application, array $attributes): array
    {
        $provider = $this->resolveProvider($application, $attributes['provider_code'] ?? null);
        $adapter = $this->providerResolver->resolve($provider->code);
        $mapping = $this->resolvePaymentMethod($provider->code, $attributes['payment_method'], $attributes['amount']);
        $storedIdempotencyKey = $this->storedIdempotencyKey($application, $attributes['idempotency_key'] ?? null);

        if ($storedIdempotencyKey !== null) {
            $existingPayment = PaymentOrder::query()
                ->with(['application:id,code', 'latestProviderTransaction'])
                ->where('idempotency_key', $storedIdempotencyKey)
                ->first();

            if ($existingPayment) {
                if (! $this->matchesIdempotentPayload($existingPayment, $application, $provider->code, $attributes)) {
                    throw new ApiException(
                        'IDEMPOTENCY_CONFLICT',
                        'Idempotency key already used with different parameters.',
                        409,
                    );
                }

                return [
                    'payment' => $existingPayment,
                    'http_status' => 200,
                ];
            }
        }

        $duplicateExternalOrder = PaymentOrder::query()
            ->where('application_id', $application->id)
            ->where('external_order_id', $attributes['external_order_id'])
            ->exists();

        if ($duplicateExternalOrder) {
            throw new ApiException(
                'VALIDATION_ERROR',
                'The given data was invalid.',
                422,
                [
                    'external_order_id' => ['The external_order_id has already been taken.'],
                ],
            );
        }

        try {
            $result = DB::transaction(function () use ($application, $attributes, $provider, $adapter, $mapping, $storedIdempotencyKey) {
                $payment = PaymentOrder::query()->create([
                    'public_id' => $this->publicId('pay_'),
                    'application_id' => $application->id,
                    'tenant_id' => $attributes['tenant_id'] ?? null,
                    'external_order_id' => $attributes['external_order_id'],
                    'idempotency_key' => $storedIdempotencyKey,
                    'merchant_ref' => $this->merchantReference($application),
                    'provider_code' => $provider->code,
                    'payment_method' => $mapping->internal_code,
                    'customer_name' => $attributes['customer']['name'],
                    'customer_email' => $attributes['customer']['email'],
                    'customer_phone' => $attributes['customer']['phone'],
                    'amount' => $attributes['amount'],
                    'currency' => $attributes['currency'] ?? 'IDR',
                    'status' => PaymentOrderStatus::Created,
                    'metadata' => $attributes['metadata'] ?? null,
                    'expires_at' => now()->addHour(),
                ]);

                $this->recordEvent($payment, 'provider.request', [
                    'provider' => $provider->code,
                    'provider_method_code' => $mapping->provider_method_code,
                    'amount' => $payment->amount,
                    'currency' => $payment->currency,
                ]);

                $providerTransaction = $this->createProviderTransaction($payment, $mapping, $provider, $adapter);

                $this->recordEvent($payment, 'provider.response', [
                    'provider' => $provider->code,
                    'provider_reference' => $providerTransaction->provider_reference,
                    'payment_method' => $mapping->provider_method_code,
                    'payment_instruction' => [
                        'payment_url' => $providerTransaction->payment_url,
                        'pay_code' => $providerTransaction->pay_code,
                        'qr_string' => $providerTransaction->qr_string,
                        'qr_url' => $providerTransaction->qr_url,
                    ],
                ]);

                $payment->forceFill([
                    'status' => PaymentOrderStatus::Pending,
                ])->save();

                $this->recordEvent($payment, 'payment.created', [
                    'status' => PaymentOrderStatus::Pending->value,
                    'provider' => $provider->code,
                    'payment_method' => $payment->payment_method,
                ]);

                $delivery = $this->createWebhookDelivery($payment, 'payment.created');

                return [
                    'payment' => $payment,
                    'delivery' => $delivery,
                    'http_status' => 201,
                ];
            });
        } catch (QueryException $exception) {
            $result = $this->recoverConcurrentCreateResult(
                $application,
                $attributes,
                $provider->code,
                $storedIdempotencyKey,
                $exception,
            );
        }

        $this->queueWebhookDelivery($result['delivery'] ?? null);

        return [
            'payment' => $result['payment']->fresh(['application:id,code', 'latestProviderTransaction']),
            'http_status' => $result['http_status'],
        ];
    }

    public function cancel(PaymentOrder $payment): PaymentOrder
    {
        $payment->loadMissing('latestProviderTransaction');

        if ($payment->status->isFinal()) {
            throw new ApiException(
                'PAYMENT_ALREADY_FINAL',
                'Payment is already in final state: '.$payment->status->value,
                409,
            );
        }

        if (! $this->canCancelPayment($payment)) {
            throw new ApiException(
                'PAYMENT_CANCELLATION_NOT_SUPPORTED',
                'Provider-side cancellation is not available for this payment. Wait until the payment expires naturally or process a refund after payment.',
                409,
            );
        }

        $result = DB::transaction(function () use ($payment) {
            $payment->forceFill([
                'status' => PaymentOrderStatus::Failed,
            ])->save();

            $this->recordEvent($payment, 'payment.failed', [
                'status' => PaymentOrderStatus::Failed->value,
                'cancelled_at' => now()->toIso8601String(),
                'source' => 'client_api',
            ]);

            $delivery = $this->createWebhookDelivery($payment, 'payment.failed');

            return [
                'payment' => $payment,
                'delivery' => $delivery,
            ];
        });

        $this->queueWebhookDelivery($result['delivery']);

        return $result['payment']->fresh(['application:id,code', 'latestProviderTransaction']);
    }

    public function refund(PaymentOrder $payment, int $amount, string $reason): PaymentOrder
    {
        if ($payment->status !== PaymentOrderStatus::Paid) {
            throw new ApiException(
                'PAYMENT_ALREADY_FINAL',
                'Payment is not eligible for refund from state: '.$payment->status->value,
                409,
            );
        }

        if ($amount > $payment->amount) {
            throw new ApiException(
                'VALIDATION_ERROR',
                'The given data was invalid.',
                422,
                [
                    'amount' => ['The refund amount may not be greater than the payment amount.'],
                ],
            );
        }

        if ($amount !== (int) $payment->amount) {
            throw new ApiException(
                'VALIDATION_ERROR',
                'The given data was invalid.',
                422,
                [
                    'amount' => ['Partial refunds are not supported. The refund amount must match the full payment amount.'],
                ],
            );
        }

        $provider = $payment->paymentProvider ?? $this->findProviderByCode($payment->provider_code, false);
        $adapter = $this->providerResolver->resolve($payment->provider_code);
        $refund = $adapter->refund($payment, $amount, $reason, $provider);

        $result = DB::transaction(function () use ($payment, $amount, $reason, $refund) {
            $payment->forceFill([
                'status' => PaymentOrderStatus::Refunded,
            ])->save();

            $latestTransaction = $payment->latestProviderTransaction;

            if ($latestTransaction) {
                $latestTransaction->forceFill([
                    'raw_response' => array_merge($latestTransaction->raw_response ?? [], [
                        'refund' => $refund['raw_response'] ?? [
                            'refund_reference' => $refund['refund_reference'] ?? null,
                            'refund_amount' => $refund['refund_amount'] ?? $amount,
                            'refund_method' => $refund['refund_method'] ?? 'api',
                        ],
                    ]),
                ])->save();
            }

            $this->recordEvent($payment, 'payment.refunded', [
                'status' => PaymentOrderStatus::Refunded->value,
                'refund_amount' => $amount,
                'reason' => $reason,
                'refund_reference' => $refund['refund_reference'] ?? null,
                'refund_method' => $refund['refund_method'] ?? 'api',
                'refunded_at' => $refund['refunded_at'] ?? now()->toIso8601String(),
            ]);

            $delivery = $this->createWebhookDelivery($payment, 'payment.refunded');

            return [
                'payment' => $payment,
                'delivery' => $delivery,
            ];
        });

        $this->queueWebhookDelivery($result['delivery']);

        return $result['payment']->fresh(['application:id,code', 'latestProviderTransaction']);
    }

    public function syncStatus(PaymentOrder $payment): array
    {
        $payment->loadMissing(['application:id,code,webhook_url', 'paymentProvider', 'latestProviderTransaction']);

        $provider = $payment->paymentProvider ?? $this->findProviderByCode($payment->provider_code, false);
        $adapter = $this->providerResolver->resolve($payment->provider_code);
        $sync = $adapter->queryTransaction($payment, $provider);
        $nextStatus = $sync['internal_status'] ?? PaymentOrderStatus::Pending;
        $providerAmount = (int) ($sync['amount'] ?? $payment->amount);

        if ($providerAmount > 0 && $providerAmount !== (int) $payment->amount) {
            throw new ApiException(
                'PROVIDER_DATA_MISMATCH',
                'Provider returned a transaction amount that does not match the payment amount.',
                409,
                [
                    'provider' => $provider->code,
                    'expected_amount' => (int) $payment->amount,
                    'provider_amount' => $providerAmount,
                ],
            );
        }

        $result = DB::transaction(function () use ($payment, $provider, $sync, $nextStatus) {
            $payment->refresh();
            $payment->loadMissing(['application:id,code,webhook_url', 'latestProviderTransaction']);

            $latestTransaction = $payment->latestProviderTransaction;
            $currentStatus = $payment->status;
            $statusChanged = $currentStatus->canTransitionFromProvider($nextStatus);
            $delivery = null;
            $eventType = null;

            if ($latestTransaction) {
                $latestTransaction->forceFill(array_filter([
                    'provider_reference' => $sync['provider_reference'] ?: $latestTransaction->provider_reference,
                    'paid_at' => $statusChanged && $nextStatus === PaymentOrderStatus::Paid
                        ? ($sync['paid_at'] ?? $latestTransaction->paid_at)
                        : $latestTransaction->paid_at,
                    'raw_response' => array_merge($latestTransaction->raw_response ?? [], [
                        'status_sync' => $sync['payload'] ?? [],
                    ]),
                ], static fn ($value) => $value !== null))->save();
            }

            if ($statusChanged) {
                $updates = [
                    'status' => $nextStatus,
                ];

                if ($nextStatus === PaymentOrderStatus::Paid && ($sync['paid_at'] ?? null) !== null) {
                    $updates['paid_at'] = $sync['paid_at'];
                } elseif ($nextStatus === PaymentOrderStatus::Paid && $payment->paid_at === null) {
                    $updates['paid_at'] = $sync['paid_at'] ?? now();
                }

                $payment->forceFill($updates)->save();

                $eventType = $this->eventTypeForStatus($nextStatus);

                if ($eventType !== null) {
                    $this->recordEvent($payment, $eventType, [
                        'status' => $nextStatus->value,
                        'provider' => $provider->code,
                        'provider_reference' => $sync['provider_reference'] ?? null,
                        'paid_at' => $payment->paid_at?->toIso8601String(),
                        'source' => 'provider_sync',
                    ]);

                    $delivery = $this->createWebhookDelivery($payment, $eventType);
                }
            }

            $this->recordEvent($payment, 'provider.status_synced', [
                'provider' => $provider->code,
                'provider_status' => $sync['provider_status'] ?? null,
                'previous_status' => $currentStatus->value,
                'current_status' => $payment->status->value,
                'transition_applied' => $statusChanged,
                'provider_reference' => $sync['provider_reference'] ?? null,
                'synced_at' => now()->toIso8601String(),
            ]);

            return [
                'payment' => $payment,
                'delivery' => $delivery,
                'status_changed' => $statusChanged,
                'provider_status' => (string) ($sync['provider_status'] ?? ''),
                'event_type' => $eventType,
                'synced_at' => now()->toIso8601String(),
            ];
        });

        $this->queueWebhookDelivery($result['delivery'] ?? null);

        return [
            'payment' => $result['payment']->fresh(['application:id,code', 'latestProviderTransaction']),
            'status_changed' => $result['status_changed'],
            'provider_status' => $result['provider_status'],
            'event_type' => $result['event_type'],
            'synced_at' => $result['synced_at'],
        ];
    }

    public function retryWebhook(WebhookDelivery $delivery): WebhookDelivery
    {
        $delivery = DB::transaction(function () use ($delivery) {
            $delivery = WebhookDelivery::query()->lockForUpdate()->findOrFail($delivery->id);

            if ($delivery->status === WebhookDeliveryStatus::Success) {
                throw new ApiException(
                    'VALIDATION_ERROR',
                    'Webhook delivery has already succeeded and cannot be retried.',
                    422,
                );
            }

            if ($delivery->status === WebhookDeliveryStatus::Pending) {
                throw new ApiException(
                    'VALIDATION_ERROR',
                    'Webhook delivery is already pending and cannot be retried yet.',
                    422,
                );
            }

            $delivery->forceFill([
                'status' => WebhookDeliveryStatus::Pending,
                'attempt' => $delivery->attempt + 1,
                'response_code' => null,
                'response_body' => null,
                'next_retry_at' => null,
            ])->save();

            return $delivery;
        });

        $this->queueWebhookDelivery($delivery);

        return $delivery;
    }

    public function handleProviderCallback(string $providerCode, Request $request): array
    {
        $provider = $this->findProviderByCode($providerCode, false);
        $adapter = $this->providerResolver->resolve($provider->code);
        $callback = $adapter->verifyCallback($request, $provider);

        if (! ($callback['is_valid'] ?? false)) {
            throw new ApiException(
                'INVALID_CALLBACK_SIGNATURE',
                'Callback signature verification failed.',
                403,
            );
        }

        $payment = PaymentOrder::query()
            ->with(['application:id,code,webhook_url', 'latestProviderTransaction'])
            ->where('provider_code', $provider->code)
            ->where('merchant_ref', $callback['merchant_ref'] ?? '')
            ->first();

        if (! $payment) {
            throw new ApiException(
                'PAYMENT_NOT_FOUND',
                'Payment not found.',
                404,
            );
        }

        if ((int) $payment->amount !== (int) ($callback['amount'] ?? 0)) {
            $this->recordEvent($payment, 'callback.rejected', [
                'provider' => $provider->code,
                'provider_status' => $callback['provider_status'] ?? null,
                'reason' => 'amount_mismatch',
                'expected_amount' => (int) $payment->amount,
                'callback_amount' => (int) ($callback['amount'] ?? 0),
                'signature_valid' => true,
            ]);

            return [
                'accepted' => true,
                'reason' => 'amount_mismatch',
            ];
        }

        $result = DB::transaction(function () use ($payment, $provider, $callback) {
            $payment->refresh();
            $payment->loadMissing(['application:id,code,webhook_url', 'latestProviderTransaction']);

            $this->recordEvent($payment, 'callback.received', [
                'provider' => $provider->code,
                'provider_status' => $callback['provider_status'] ?? null,
                'internal_status' => ($callback['internal_status'] ?? null)?->value,
                'signature_valid' => true,
                'provider_reference' => $callback['provider_reference'] ?? null,
            ]);

            $nextStatus = $callback['internal_status'] ?? PaymentOrderStatus::Pending;
            $currentStatus = $payment->status;
            $statusChanged = $currentStatus->canTransitionFromProvider($nextStatus);
            $latestTransaction = $payment->latestProviderTransaction;

            if ($latestTransaction) {
                $latestTransaction->forceFill(array_filter([
                    'provider_reference' => $callback['provider_reference'] ?: $latestTransaction->provider_reference,
                    'paid_at' => $statusChanged && $nextStatus === PaymentOrderStatus::Paid
                        ? ($callback['paid_at'] ?? $latestTransaction->paid_at)
                        : $latestTransaction->paid_at,
                    'raw_response' => array_merge($latestTransaction->raw_response ?? [], [
                        'callback' => $callback['payload'] ?? [],
                    ]),
                ], static fn ($value) => $value !== null))->save();
            }

            if (! $statusChanged) {
                return [
                    'accepted' => true,
                    'status_changed' => false,
                    'payment' => $payment,
                    'delivery' => null,
                ];
            }

            $updates = [
                'status' => $nextStatus,
            ];

            if ($nextStatus === PaymentOrderStatus::Paid && ($callback['paid_at'] ?? null) !== null) {
                $updates['paid_at'] = $callback['paid_at'];
            } elseif ($nextStatus === PaymentOrderStatus::Paid && $payment->paid_at === null) {
                $updates['paid_at'] = $callback['paid_at'] ?? now();
            }

            $payment->forceFill($updates)->save();

            $eventType = $this->eventTypeForStatus($nextStatus);
            $delivery = null;

            if ($eventType !== null) {
                $this->recordEvent($payment, $eventType, [
                    'status' => $nextStatus->value,
                    'provider' => $provider->code,
                    'provider_reference' => $callback['provider_reference'] ?? null,
                    'paid_at' => $payment->paid_at?->toIso8601String(),
                ]);

                $delivery = $this->createWebhookDelivery($payment, $eventType);
            }

            return [
                'accepted' => true,
                'status_changed' => $statusChanged,
                'event_type' => $eventType,
                'payment' => $payment,
                'delivery' => $delivery,
            ];
        });

        $this->queueWebhookDelivery($result['delivery'] ?? null);

        unset($result['payment'], $result['delivery']);

        return $result;
    }

    public function dispatchDueWebhookRetries(int $limit = 100): int
    {
        $count = 0;

        $dueDeliveryIds = WebhookDelivery::query()
            ->where('status', WebhookDeliveryStatus::Failed->value)
            ->whereNotNull('next_retry_at')
            ->where('next_retry_at', '<=', now())
            ->orderBy('next_retry_at')
            ->limit($limit)
            ->pluck('id');

        foreach ($dueDeliveryIds as $deliveryId) {
            $delivery = DB::transaction(function () use ($deliveryId) {
                $delivery = WebhookDelivery::query()->lockForUpdate()->find($deliveryId);

                if (! $delivery || $delivery->status !== WebhookDeliveryStatus::Failed) {
                    return null;
                }

                if (! $delivery->next_retry_at || $delivery->next_retry_at->isFuture()) {
                    return null;
                }

                $delivery->forceFill([
                    'status' => WebhookDeliveryStatus::Pending,
                    'attempt' => $delivery->attempt + 1,
                    'response_code' => null,
                    'response_body' => null,
                    'next_retry_at' => null,
                ])->save();

                return $delivery;
            });

            if (! $delivery instanceof WebhookDelivery) {
                continue;
            }

            $this->queueWebhookDelivery($delivery);
            $count++;
        }

        return $count;
    }

    protected function queueWebhookDelivery(?WebhookDelivery $delivery): ?WebhookDelivery
    {
        if (! $delivery) {
            return null;
        }

        $this->webhookService->queue($delivery);

        return $delivery;
    }

    protected function resolveProvider(Application $application, ?string $providerCode): PaymentProvider
    {
        $resolvedProviderCode = $providerCode ?: $application->default_provider;

        return $this->findProviderByCode($resolvedProviderCode);
    }

    protected function resolvePaymentMethod(string $providerCode, string $internalCode, int $amount): PaymentMethodMapping
    {
        $mapping = PaymentMethodMapping::query()
            ->where('provider_code', $providerCode)
            ->where('internal_code', $internalCode)
            ->where('is_active', true)
            ->where(function ($query) use ($amount) {
                $query->whereNull('min_amount')->orWhere('min_amount', '<=', $amount);
            })
            ->where(function ($query) use ($amount) {
                $query->whereNull('max_amount')->orWhere('max_amount', '>=', $amount);
            })
            ->first();

        if (! $mapping) {
            throw new ApiException(
                'PAYMENT_METHOD_NOT_AVAILABLE',
                'Payment method is not available for the selected provider.',
                422,
            );
        }

        return $mapping;
    }

    protected function createProviderTransaction(
        PaymentOrder $payment,
        PaymentMethodMapping $mapping,
        PaymentProvider $provider,
        PaymentProviderInterface $adapter
    ): ProviderTransaction {
        $transaction = $adapter->createTransaction($payment, $mapping, $provider);

        return $payment->providerTransactions()->create([
            'provider' => $provider->code,
            'merchant_ref' => $payment->merchant_ref,
            'provider_reference' => $transaction['provider_reference'] ?? strtoupper($provider->code.'_'.Str::random(12)),
            'payment_method' => $transaction['payment_method'] ?? $mapping->provider_method_code,
            'payment_url' => $transaction['payment_url'] ?? null,
            'pay_code' => $transaction['pay_code'] ?? null,
            'qr_string' => $transaction['qr_string'] ?? null,
            'qr_url' => $transaction['qr_url'] ?? null,
            'raw_request' => $transaction['raw_request'] ?? [],
            'raw_response' => $transaction['raw_response'] ?? [],
        ]);
    }

    protected function createWebhookDelivery(PaymentOrder $payment, string $eventType): WebhookDelivery
    {
        $payment->load('application:id,code,webhook_url');

        return $payment->webhookDeliveries()->create([
            'public_id' => $this->publicId('wh_'),
            'application_id' => $payment->application_id,
            'event_type' => $eventType,
            'target_url' => $payment->application->webhook_url,
            'request_body' => $this->webhookPayload($payment, $eventType),
            'attempt' => 1,
            'status' => WebhookDeliveryStatus::Pending,
            'created_at' => now(),
        ]);
    }

    protected function webhookPayload(PaymentOrder $payment, string $eventType): array
    {
        $payment->loadMissing(['application:id,code', 'latestProviderTransaction']);

        return [
            'event' => $eventType,
            'payment_id' => $payment->public_id,
            'application_code' => $payment->application->code,
            'external_order_id' => $payment->external_order_id,
            'merchant_ref' => $payment->merchant_ref,
            'provider' => $payment->provider_code,
            'payment_method' => $payment->payment_method,
            'amount' => $payment->amount,
            'currency' => $payment->currency,
            'status' => $payment->status->value,
            'customer' => [
                'name' => $payment->customer_name,
                'email' => $payment->customer_email,
                'phone' => $payment->customer_phone,
            ],
            'paid_at' => $payment->paid_at?->toIso8601String(),
            'metadata' => $payment->metadata,
            'timestamp' => now()->toIso8601String(),
        ];
    }

    protected function recordEvent(PaymentOrder $payment, string $eventType, array $payload): PaymentEvent
    {
        return $payment->paymentEvents()->create([
            'public_id' => $this->publicId('evt_'),
            'event_type' => $eventType,
            'payload' => $payload,
            'created_at' => now(),
        ]);
    }

    protected function matchesIdempotentPayload(PaymentOrder $payment, Application $application, string $providerCode, array $attributes): bool
    {
        $expected = $this->normalizeArray([
            'application_code' => $application->code,
            'external_order_id' => $attributes['external_order_id'],
            'amount' => (int) $attributes['amount'],
            'currency' => $attributes['currency'] ?? 'IDR',
            'payment_method' => $attributes['payment_method'],
            'provider_code' => $providerCode,
            'customer' => [
                'name' => $attributes['customer']['name'],
                'email' => $attributes['customer']['email'],
                'phone' => $attributes['customer']['phone'],
            ],
            'metadata' => $attributes['metadata'] ?? null,
        ]);

        $actual = $this->normalizeArray([
            'application_code' => $payment->application->code,
            'external_order_id' => $payment->external_order_id,
            'amount' => (int) $payment->amount,
            'currency' => $payment->currency,
            'payment_method' => $payment->payment_method,
            'provider_code' => $payment->provider_code,
            'customer' => [
                'name' => $payment->customer_name,
                'email' => $payment->customer_email,
                'phone' => $payment->customer_phone,
            ],
            'metadata' => $payment->metadata,
        ]);

        return $expected === $actual;
    }

    protected function normalizeArray(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        foreach ($value as $key => $nestedValue) {
            $value[$key] = $this->normalizeArray($nestedValue);
        }

        if (Arr::isAssoc($value)) {
            ksort($value);
        }

        return $value;
    }

    protected function recoverConcurrentCreateResult(
        Application $application,
        array $attributes,
        string $providerCode,
        ?string $storedIdempotencyKey,
        QueryException $exception
    ): array {
        if (! $this->isDuplicateKeyViolation($exception)) {
            throw $exception;
        }

        if ($storedIdempotencyKey !== null) {
            $existingPayment = PaymentOrder::query()
                ->with(['application:id,code', 'latestProviderTransaction'])
                ->where('application_id', $application->id)
                ->where('idempotency_key', $storedIdempotencyKey)
                ->first();

            if ($existingPayment) {
                if (! $this->matchesIdempotentPayload($existingPayment, $application, $providerCode, $attributes)) {
                    throw new ApiException(
                        'IDEMPOTENCY_CONFLICT',
                        'Idempotency key already used with different parameters.',
                        409,
                    );
                }

                return [
                    'payment' => $existingPayment,
                    'delivery' => null,
                    'http_status' => 200,
                ];
            }
        }

        $duplicateExternalOrder = PaymentOrder::query()
            ->where('application_id', $application->id)
            ->where('external_order_id', $attributes['external_order_id'])
            ->exists();

        if ($duplicateExternalOrder) {
            throw new ApiException(
                'VALIDATION_ERROR',
                'The given data was invalid.',
                422,
                [
                    'external_order_id' => ['The external_order_id has already been taken.'],
                ],
            );
        }

        throw $exception;
    }

    protected function isDuplicateKeyViolation(QueryException $exception): bool
    {
        $sqlState = (string) ($exception->errorInfo[0] ?? $exception->getCode());
        $driverCode = (int) ($exception->errorInfo[1] ?? 0);
        $message = strtolower($exception->getMessage());

        if (in_array($sqlState, ['23000', '23505'], true)) {
            return true;
        }

        if (in_array($driverCode, [19, 1062, 1555, 2067], true)) {
            return true;
        }

        return str_contains($message, 'duplicate')
            || str_contains($message, 'unique constraint')
            || str_contains($message, 'integrity constraint violation');
    }

    protected function storedIdempotencyKey(Application $application, ?string $idempotencyKey): ?string
    {
        if (! is_string($idempotencyKey) || $idempotencyKey === '') {
            return null;
        }

        return hash('sha256', $application->id.'|'.$idempotencyKey);
    }

    protected function publicId(string $prefix): string
    {
        return $prefix.Str::lower((string) Str::ulid());
    }

    protected function merchantReference(Application $application): string
    {
        do {
            $merchantRef = sprintf('%s-%s-%s', $application->code, now()->format('Ymd'), strtoupper(Str::random(6)));
        } while (PaymentOrder::query()->where('merchant_ref', $merchantRef)->exists());

        return $merchantRef;
    }

    protected function findProviderByCode(string $providerCode, bool $requireActive = true): PaymentProvider
    {
        $provider = PaymentProvider::query()->where('code', $providerCode)->first();

        if (! $provider) {
            throw new ApiException(
                'PROVIDER_NOT_FOUND',
                'Provider code is not recognized.',
                422,
            );
        }

        if ($requireActive && ! $provider->is_active) {
            throw new ApiException(
                'PROVIDER_INACTIVE',
                'Provider is inactive.',
                422,
            );
        }

        return $provider;
    }

    protected function canCancelPayment(PaymentOrder $payment): bool
    {
        return $payment->status === PaymentOrderStatus::Created
            && ! $payment->latestProviderTransaction;
    }

    protected function eventTypeForStatus(PaymentOrderStatus $status): ?string
    {
        return match ($status) {
            PaymentOrderStatus::Paid => 'payment.paid',
            PaymentOrderStatus::Failed => 'payment.failed',
            PaymentOrderStatus::Expired => 'payment.expired',
            PaymentOrderStatus::Refunded => 'payment.refunded',
            default => null,
        };
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
}
