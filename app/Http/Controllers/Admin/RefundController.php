<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\Admin\RefundIndexRequest;
use App\Models\Application;
use App\Models\PaymentOrder;
use App\Models\PaymentProvider;
use Illuminate\Contracts\View\View;

class RefundController extends AdminController
{
    public function index(RefundIndexRequest $request): View
    {
        $filters = $request->validated();

        $orders = PaymentOrder::query()
            ->with(['application', 'paymentProvider', 'latestProviderTransaction'])
            ->whereIn('status', ['PAID', 'REFUNDED'])
            ->when($filters['application'] ?? null, fn ($query, $code) => $query->whereHas('application', fn ($applicationQuery) => $applicationQuery->where('code', $code)))
            ->when($filters['provider'] ?? null, fn ($query, $code) => $query->where('provider_code', $code))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', strtoupper($status)))
            ->latest('updated_at')
            ->paginate(15)
            ->withQueryString();

        $summary = PaymentOrder::query()
            ->whereIn('status', ['PAID', 'REFUNDED'])
            ->when($filters['application'] ?? null, fn ($query, $code) => $query->whereHas('application', fn ($applicationQuery) => $applicationQuery->where('code', $code)))
            ->when($filters['provider'] ?? null, fn ($query, $code) => $query->where('provider_code', $code))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', strtoupper($status)))
            ->selectRaw("
                SUM(CASE WHEN status = 'PAID' THEN 1 ELSE 0 END) as paid_orders,
                SUM(CASE WHEN status = 'REFUNDED' THEN 1 ELSE 0 END) as refunded_orders
            ")
            ->first();

        return $this->renderPage('admin.refunds.index', [
            'title' => 'Refunds',
            'heading' => 'Refund Operations',
            'kicker' => 'Operations',
            'description' => 'Monitoring order yang sudah paid dan refunded agar proses refund tetap mudah diaudit sebelum workflow penuh ditambahkan.',
        ], [
            'orders' => $orders,
            'filters' => $filters,
            'applications' => Application::query()->orderBy('name')->pluck('name', 'code'),
            'providers' => PaymentProvider::query()->orderBy('name')->pluck('name', 'code'),
            'summary' => [
                'paid_orders' => (int) ($summary->paid_orders ?? 0),
                'refunded_orders' => (int) ($summary->refunded_orders ?? 0),
            ],
        ]);
    }
}
