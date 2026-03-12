<?php

namespace App\Services\Admin;

use App\Enums\PaymentOrderStatus;
use App\Enums\WebhookDeliveryStatus;
use App\Models\Application;
use App\Models\PaymentEvent;
use App\Models\PaymentMethodMapping;
use App\Models\PaymentOrder;
use App\Models\PaymentProvider;
use App\Models\WebhookDelivery;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DashboardService
{
    public function build(): array
    {
        $statusSummary = PaymentOrder::query()
            ->toBase()
            ->select(
                'status',
                DB::raw('COUNT(*) as total_orders'),
                DB::raw('COALESCE(SUM(amount), 0) as gross_amount')
            )
            ->groupBy('status')
            ->get()
            ->keyBy('status');

        $statusCards = collect(PaymentOrderStatus::cases())->map(function (PaymentOrderStatus $status) use ($statusSummary) {
            $summary = $statusSummary->get($status->value);

            return [
                'label' => $status->value,
                'count' => (int) data_get($summary, 'total_orders', 0),
                'amount' => (int) data_get($summary, 'gross_amount', 0),
                'tone' => $status->tone(),
            ];
        });

        $totalTransactions = $statusCards->sum('count');
        $grossVolume = $statusCards->sum('amount');
        $paidCount = (int) optional($statusSummary->get(PaymentOrderStatus::Paid->value))->total_orders;
        $pendingCount = (int) optional($statusSummary->get(PaymentOrderStatus::Pending->value))->total_orders
            + (int) optional($statusSummary->get(PaymentOrderStatus::Created->value))->total_orders;
        $problemCount = (int) optional($statusSummary->get(PaymentOrderStatus::Failed->value))->total_orders
            + (int) optional($statusSummary->get(PaymentOrderStatus::Expired->value))->total_orders;
        $refundedCount = (int) optional($statusSummary->get(PaymentOrderStatus::Refunded->value))->total_orders;
        $paidAmount = (int) optional($statusSummary->get(PaymentOrderStatus::Paid->value))->gross_amount;

        $paymentMethodCounts = PaymentMethodMapping::query()
            ->toBase()
            ->select(
                'provider_code',
                DB::raw('COUNT(*) as total_methods'),
                DB::raw('SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active_methods')
            )
            ->groupBy('provider_code')
            ->get()
            ->keyBy('provider_code');

        $providerOrderCounts = PaymentOrder::query()
            ->toBase()
            ->select(
                'provider_code',
                DB::raw('COUNT(*) as total_orders'),
                DB::raw('COALESCE(SUM(amount), 0) as gross_amount'),
                DB::raw("SUM(CASE WHEN status = 'PAID' THEN 1 ELSE 0 END) as paid_orders"),
                DB::raw("SUM(CASE WHEN status IN ('FAILED', 'EXPIRED') THEN 1 ELSE 0 END) as problem_orders")
            )
            ->groupBy('provider_code')
            ->get()
            ->keyBy('provider_code');

        $providerWebhookCounts = WebhookDelivery::query()
            ->join('payment_orders', 'payment_orders.id', '=', 'webhook_deliveries.payment_order_id')
            ->toBase()
            ->select(
                'payment_orders.provider_code',
                DB::raw('COUNT(*) as total_deliveries'),
                DB::raw("SUM(CASE WHEN webhook_deliveries.status = 'success' THEN 1 ELSE 0 END) as success_deliveries"),
                DB::raw("SUM(CASE WHEN webhook_deliveries.status = 'failed' THEN 1 ELSE 0 END) as failed_deliveries")
            )
            ->groupBy('payment_orders.provider_code')
            ->get()
            ->keyBy('provider_code');

        $settlementAverages = PaymentOrder::query()
            ->whereNotNull('paid_at')
            ->get(['provider_code', 'created_at', 'paid_at'])
            ->groupBy('provider_code')
            ->map(
                static fn (Collection $orders) => (int) round(
                    $orders->avg(
                        static fn (PaymentOrder $order) => $order->created_at->diffInMinutes($order->paid_at)
                    ) ?? 0
                )
            );

        $providerCards = PaymentProvider::query()
            ->orderBy('name')
            ->get()
            ->map(function (PaymentProvider $provider) use (
                $paymentMethodCounts,
                $providerOrderCounts,
                $providerWebhookCounts,
                $settlementAverages,
                $totalTransactions
            ) {
                $orderCounts = $providerOrderCounts->get($provider->code);
                $webhookCounts = $providerWebhookCounts->get($provider->code);
                $methodCounts = $paymentMethodCounts->get($provider->code);
                $totalOrders = (int) data_get($orderCounts, 'total_orders', 0);
                $paidOrders = (int) data_get($orderCounts, 'paid_orders', 0);
                $problemOrders = (int) data_get($orderCounts, 'problem_orders', 0);
                $totalDeliveries = (int) data_get($webhookCounts, 'total_deliveries', 0);
                $successfulDeliveries = (int) data_get($webhookCounts, 'success_deliveries', 0);
                $failedDeliveries = (int) data_get($webhookCounts, 'failed_deliveries', 0);
                $paidRate = $totalOrders > 0 ? round(($paidOrders / $totalOrders) * 100, 1) : 0;
                $webhookRate = $totalDeliveries > 0 ? round(($successfulDeliveries / $totalDeliveries) * 100, 1) : null;
                [$healthLabel, $healthTone] = $this->providerHealth(
                    $provider->is_active,
                    $paidRate,
                    $webhookRate,
                    $problemOrders
                );

                return [
                    'code' => $provider->code,
                    'name' => $provider->name,
                    'mode' => $provider->sandbox_mode ? 'Sandbox' : 'Production',
                    'is_active' => $provider->is_active,
                    'health_label' => $healthLabel,
                    'health_tone' => $healthTone,
                    'activity_share' => $totalTransactions > 0 ? round(($totalOrders / $totalTransactions) * 100, 1) : 0,
                    'total_orders' => $totalOrders,
                    'gross_amount' => (int) data_get($orderCounts, 'gross_amount', 0),
                    'paid_rate' => $paidRate,
                    'webhook_rate' => $webhookRate,
                    'active_methods' => (int) data_get($methodCounts, 'active_methods', 0),
                    'total_methods' => (int) data_get($methodCounts, 'total_methods', 0),
                    'failed_deliveries' => $failedDeliveries,
                    'average_settlement_minutes' => $settlementAverages->get($provider->code),
                ];
            });

        $applicationWebhookFailures = WebhookDelivery::query()
            ->toBase()
            ->select(
                'application_id',
                DB::raw("SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed_deliveries")
            )
            ->groupBy('application_id')
            ->get()
            ->keyBy('application_id');

        $applicationCards = Application::query()
            ->with('defaultProvider')
            ->withCount('paymentOrders')
            ->withCount([
                'paymentOrders as paid_orders_count' => fn ($query) => $query->where('status', PaymentOrderStatus::Paid->value),
            ])
            ->withSum('paymentOrders as gross_amount', 'amount')
            ->orderByDesc('payment_orders_count')
            ->limit(5)
            ->get()
            ->map(function (Application $application) use ($applicationWebhookFailures) {
                return [
                    'code' => $application->code,
                    'name' => $application->name,
                    'default_provider' => $application->default_provider,
                    'is_active' => $application->status,
                    'total_orders' => (int) $application->payment_orders_count,
                    'gross_amount' => (int) ($application->gross_amount ?? 0),
                    'paid_rate' => $application->payment_orders_count > 0
                        ? round(($application->paid_orders_count / $application->payment_orders_count) * 100, 1)
                        : 0,
                    'failed_deliveries' => (int) data_get($applicationWebhookFailures, $application->id . '.failed_deliveries', 0),
                ];
            });

        $paymentMethodShare = PaymentOrder::query()
            ->toBase()
            ->select(
                'payment_method',
                DB::raw('COUNT(*) as total_orders'),
                DB::raw('COALESCE(SUM(amount), 0) as gross_amount')
            )
            ->groupBy('payment_method')
            ->orderByDesc('total_orders')
            ->limit(5)
            ->get()
            ->map(fn ($method) => [
                'code' => $method->payment_method,
                'total_orders' => (int) $method->total_orders,
                'gross_amount' => (int) $method->gross_amount,
                'share' => $totalTransactions > 0 ? round(((int) $method->total_orders / $totalTransactions) * 100, 1) : 0,
            ]);

        $latestTransactions = PaymentOrder::query()
            ->with(['application', 'latestProviderTransaction'])
            ->latestFirst()
            ->limit(8)
            ->get();

        $latestEvents = PaymentEvent::query()
            ->with('paymentOrder.application')
            ->latest('created_at')
            ->limit(8)
            ->get();

        $latestWebhookDeliveries = WebhookDelivery::query()
            ->with('paymentOrder.application')
            ->orderByRaw("CASE status WHEN 'failed' THEN 0 WHEN 'pending' THEN 1 ELSE 2 END")
            ->latest('created_at')
            ->limit(8)
            ->get();

        $webhookSummary = WebhookDelivery::query()
            ->toBase()
            ->select(
                DB::raw('COUNT(*) as total_deliveries'),
                DB::raw("SUM(CASE WHEN status = 'success' THEN 1 ELSE 0 END) as success_deliveries"),
                DB::raw("SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_deliveries"),
                DB::raw("SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed_deliveries")
            )
            ->first();

        $webhookSuccessRate = (int) data_get($webhookSummary, 'total_deliveries', 0) > 0
            ? round(((int) $webhookSummary->success_deliveries / (int) $webhookSummary->total_deliveries) * 100, 1)
            : 0;

        return [
            'summaryCards' => [
                [
                    'label' => 'Total transactions',
                    'value' => $totalTransactions,
                    'caption' => 'Gross volume',
                    'amount' => $grossVolume,
                    'tone' => 'cyan',
                ],
                [
                    'label' => 'Paid conversion',
                    'value' => $totalTransactions > 0 ? round(($paidCount / $totalTransactions) * 100, 1) : 0,
                    'caption' => 'Successful payments',
                    'amount' => $paidCount,
                    'tone' => 'emerald',
                ],
                [
                    'label' => 'Pending queue',
                    'value' => $pendingCount,
                    'caption' => 'Orders still waiting for completion',
                    'amount' => null,
                    'tone' => 'amber',
                ],
                [
                    'label' => 'Problem cases',
                    'value' => $problemCount + $refundedCount,
                    'caption' => 'Failed, expired, and refunded payments',
                    'amount' => null,
                    'tone' => 'rose',
                ],
                [
                    'label' => 'Webhook success',
                    'value' => $webhookSuccessRate,
                    'caption' => 'Successful delivery rate',
                    'amount' => (int) data_get($webhookSummary, 'pending_deliveries', 0),
                    'tone' => 'violet',
                ],
            ],
            'statusCards' => $statusCards->map(fn (array $card) => [
                ...$card,
                'ratio' => $totalTransactions > 0 ? round(($card['count'] / $totalTransactions) * 100, 1) : 0,
            ]),
            'providerCards' => $providerCards,
            'applicationCards' => $applicationCards,
            'paymentMethodShare' => $paymentMethodShare,
            'latestTransactions' => $latestTransactions,
            'latestEvents' => $latestEvents,
            'latestWebhookDeliveries' => $latestWebhookDeliveries,
            'reconciliationSummary' => [
                'missing_provider_transaction' => PaymentOrder::query()->doesntHave('providerTransactions')->count(),
                'missing_successful_webhook' => PaymentOrder::query()
                    ->where('status', PaymentOrderStatus::Paid)
                    ->whereDoesntHave('webhookDeliveries', fn ($query) => $query->where('status', WebhookDeliveryStatus::Success->value))
                    ->count(),
                'paid_without_paid_event' => PaymentOrder::query()
                    ->where('status', PaymentOrderStatus::Paid)
                    ->whereDoesntHave('paymentEvents', fn ($query) => $query->where('event_type', 'payment.paid'))
                    ->count(),
                'expired_but_still_pending' => PaymentOrder::query()
                    ->whereIn('status', [PaymentOrderStatus::Created, PaymentOrderStatus::Pending])
                    ->whereNotNull('expires_at')
                    ->where('expires_at', '<', now())
                    ->count(),
            ],
            'paidAmount' => $paidAmount,
        ];
    }

    protected function providerHealth(bool $isActive, float $paidRate, ?float $webhookRate, int $problemOrders): array
    {
        if (! $isActive) {
            return ['Standby', 'slate'];
        }

        if ($paidRate >= 90 && ($webhookRate === null || $webhookRate >= 95) && $problemOrders <= 5) {
            return ['Optimal', 'emerald'];
        }

        if ($paidRate >= 75 && ($webhookRate === null || $webhookRate >= 85)) {
            return ['Watch', 'amber'];
        }

        return ['Degraded', 'rose'];
    }
}
