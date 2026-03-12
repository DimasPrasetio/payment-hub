<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\Admin\TransactionIndexRequest;
use App\Models\Application;
use App\Models\PaymentOrder;
use App\Models\PaymentProvider;
use Illuminate\Contracts\View\View;

class PaymentOrderController extends AdminController
{
    public function index(TransactionIndexRequest $request): View
    {
        $filters = $request->validated();

        $orders = PaymentOrder::query()
            ->with(['application', 'paymentProvider', 'latestProviderTransaction'])
            ->when($filters['q'] ?? null, function ($query, $search) {
                $query->where(function ($searchQuery) use ($search) {
                    $searchQuery
                        ->where('public_id', 'like', "%{$search}%")
                        ->orWhere('merchant_ref', 'like', "%{$search}%")
                        ->orWhere('external_order_id', 'like', "%{$search}%")
                        ->orWhere('customer_name', 'like', "%{$search}%")
                        ->orWhere('customer_email', 'like', "%{$search}%");
                });
            })
            ->when($filters['application'] ?? null, fn ($query, $code) => $query->whereHas('application', fn ($applicationQuery) => $applicationQuery->where('code', $code)))
            ->when($filters['provider'] ?? null, fn ($query, $code) => $query->where('provider_code', $code))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['payment_method'] ?? null, fn ($query, $method) => $query->where('payment_method', $method))
            ->when($filters['date_from'] ?? null, fn ($query, $date) => $query->whereDate('created_at', '>=', $date))
            ->when($filters['date_to'] ?? null, fn ($query, $date) => $query->whereDate('created_at', '<=', $date))
            ->latestFirst()
            ->paginate(15)
            ->withQueryString();

        $summary = PaymentOrder::query()
            ->when($filters['application'] ?? null, fn ($query, $code) => $query->whereHas('application', fn ($applicationQuery) => $applicationQuery->where('code', $code)))
            ->when($filters['provider'] ?? null, fn ($query, $code) => $query->where('provider_code', $code))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['payment_method'] ?? null, fn ($query, $method) => $query->where('payment_method', $method))
            ->when($filters['date_from'] ?? null, fn ($query, $date) => $query->whereDate('created_at', '>=', $date))
            ->when($filters['date_to'] ?? null, fn ($query, $date) => $query->whereDate('created_at', '<=', $date))
            ->selectRaw('COUNT(*) as total_orders, COALESCE(SUM(amount), 0) as gross_amount')
            ->first();

        return $this->renderPage('admin.transactions.index', [
            'title' => 'Transactions',
            'heading' => 'Transaction Monitor',
            'kicker' => 'Control Center',
            'description' => 'Daftar payment order lintas aplikasi dan provider dengan filter yang relevan untuk monitoring operasional.',
        ], [
            'orders' => $orders,
            'filters' => $filters,
            'applications' => Application::query()->orderBy('name')->pluck('name', 'code'),
            'providers' => PaymentProvider::query()->orderBy('name')->pluck('name', 'code'),
            'paymentMethods' => PaymentOrder::query()->distinct()->orderBy('payment_method')->pluck('payment_method'),
            'summary' => [
                'total_orders' => (int) ($summary->total_orders ?? 0),
                'gross_amount' => (int) ($summary->gross_amount ?? 0),
            ],
        ]);
    }

    public function show(PaymentOrder $paymentOrder): View
    {
        $paymentOrder->load([
            'application.defaultProvider',
            'paymentProvider',
            'providerTransactions' => fn ($query) => $query->latest('created_at'),
            'paymentEvents' => fn ($query) => $query->latest('created_at'),
            'webhookDeliveries' => fn ($query) => $query->latest('created_at'),
        ]);

        return $this->renderPage('admin.transactions.show', [
            'title' => "Transaction {$paymentOrder->public_id}",
            'heading' => $paymentOrder->public_id,
            'kicker' => 'Transaction Detail',
            'description' => 'Alur lengkap transaksi dari order internal, provider transaction, event audit trail, sampai webhook delivery.',
        ], [
            'paymentOrder' => $paymentOrder,
        ]);
    }
}
