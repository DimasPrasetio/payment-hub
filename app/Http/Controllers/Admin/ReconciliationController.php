<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\Admin\ReconciliationIndexRequest;
use App\Models\Application;
use App\Models\PaymentOrder;
use App\Models\PaymentProvider;
use Illuminate\Contracts\View\View;

class ReconciliationController extends AdminController
{
    public function index(ReconciliationIndexRequest $request): View
    {
        $filters = $request->validated();

        $orders = PaymentOrder::query()
            ->with(['application', 'paymentProvider', 'providerTransactions', 'paymentEvents', 'webhookDeliveries'])
            ->when($filters['application'] ?? null, fn ($query, $code) => $query->whereHas('application', fn ($applicationQuery) => $applicationQuery->where('code', $code)))
            ->when($filters['provider'] ?? null, fn ($query, $code) => $query->where('provider_code', $code))
            ->when($filters['issue'] ?? null, fn ($query, $issue) => $this->applyIssueFilter($query, $issue), fn ($query) => $this->applyAllIssueFilters($query))
            ->latest('created_at')
            ->paginate(15)
            ->withQueryString();

        $orders->through(function (PaymentOrder $order) {
            $order->setAttribute('reconciliation_issues', $this->issuesForOrder($order));

            return $order;
        });

        return $this->renderPage('admin.reconciliation.index', [
            'title' => 'Reconciliation',
            'heading' => 'Manual Reconciliation Workspace',
            'kicker' => 'Operations',
            'description' => 'Daftar transaksi yang berpotensi tidak sinkron antara order internal, provider transaction, event audit trail, dan webhook delivery.',
        ], [
            'orders' => $orders,
            'filters' => $filters,
            'applications' => Application::query()->orderBy('name')->pluck('name', 'code'),
            'providers' => PaymentProvider::query()->orderBy('name')->pluck('name', 'code'),
        ]);
    }

    protected function applyAllIssueFilters($query)
    {
        return $query
            ->where(function ($issueQuery) {
                $this->applyIssueFilter($issueQuery, 'missing_provider_transaction')
                    ->orWhere(function ($nestedQuery) {
                        $this->applyIssueFilter($nestedQuery, 'missing_successful_webhook');
                    })
                    ->orWhere(function ($nestedQuery) {
                        $this->applyIssueFilter($nestedQuery, 'paid_without_paid_event');
                    })
                    ->orWhere(function ($nestedQuery) {
                        $this->applyIssueFilter($nestedQuery, 'expired_but_still_pending');
                    });
            });
    }

    protected function applyIssueFilter($query, string $issue)
    {
        return match ($issue) {
            'missing_provider_transaction' => $query->doesntHave('providerTransactions'),
            'missing_successful_webhook' => $query
                ->where('status', 'PAID')
                ->whereDoesntHave('webhookDeliveries', fn ($deliveryQuery) => $deliveryQuery->where('status', 'success')),
            'paid_without_paid_event' => $query
                ->where('status', 'PAID')
                ->whereDoesntHave('paymentEvents', fn ($eventQuery) => $eventQuery->where('event_type', 'payment.paid')),
            'expired_but_still_pending' => $query
                ->whereIn('status', ['CREATED', 'PENDING'])
                ->whereNotNull('expires_at')
                ->where('expires_at', '<', now()),
            default => $query,
        };
    }

    protected function issuesForOrder(PaymentOrder $order): array
    {
        $issues = [];

        if ($order->providerTransactions->isEmpty()) {
            $issues[] = 'Missing provider transaction';
        }

        if (
            $order->status?->value === 'PAID'
            && $order->webhookDeliveries->filter(
                static fn ($delivery) => $delivery->status?->value === 'success'
            )->isEmpty()
        ) {
            $issues[] = 'Missing successful webhook';
        }

        if ($order->status?->value === 'PAID' && $order->paymentEvents->where('event_type', 'payment.paid')->isEmpty()) {
            $issues[] = 'Missing payment.paid event';
        }

        if (in_array($order->status?->value, ['CREATED', 'PENDING'], true) && $order->expires_at?->isPast()) {
            $issues[] = 'Expired but still pending';
        }

        return $issues;
    }
}
