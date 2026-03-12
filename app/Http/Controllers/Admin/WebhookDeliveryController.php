<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\Admin\WebhookDeliveryIndexRequest;
use App\Models\Application;
use App\Models\PaymentProvider;
use App\Models\WebhookDelivery;
use Illuminate\Contracts\View\View;

class WebhookDeliveryController extends AdminController
{
    public function index(WebhookDeliveryIndexRequest $request): View
    {
        $filters = $request->validated();

        $deliveries = WebhookDelivery::query()
            ->with(['application', 'paymentOrder.application'])
            ->when($filters['application'] ?? null, fn ($query, $code) => $query->whereHas('application', fn ($applicationQuery) => $applicationQuery->where('code', $code)))
            ->when($filters['provider'] ?? null, fn ($query, $code) => $query->whereHas('paymentOrder', fn ($orderQuery) => $orderQuery->where('provider_code', $code)))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['event_type'] ?? null, fn ($query, $eventType) => $query->where('event_type', $eventType))
            ->when($filters['payment'] ?? null, fn ($query, $paymentId) => $query->whereHas('paymentOrder', fn ($orderQuery) => $orderQuery->where('public_id', $paymentId)))
            ->when($filters['date_from'] ?? null, fn ($query, $date) => $query->whereDate('created_at', '>=', $date))
            ->when($filters['date_to'] ?? null, fn ($query, $date) => $query->whereDate('created_at', '<=', $date))
            ->orderByRaw("CASE status WHEN 'failed' THEN 0 WHEN 'pending' THEN 1 ELSE 2 END")
            ->latest('created_at')
            ->paginate(20)
            ->withQueryString();

        $summary = WebhookDelivery::query()
            ->when($filters['application'] ?? null, fn ($query, $code) => $query->whereHas('application', fn ($applicationQuery) => $applicationQuery->where('code', $code)))
            ->when($filters['provider'] ?? null, fn ($query, $code) => $query->whereHas('paymentOrder', fn ($orderQuery) => $orderQuery->where('provider_code', $code)))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->selectRaw("
                COUNT(*) as total_deliveries,
                SUM(CASE WHEN status = 'success' THEN 1 ELSE 0 END) as successful_deliveries,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_deliveries,
                SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed_deliveries
            ")
            ->first();

        return $this->renderPage('admin.webhooks.index', [
            'title' => 'Webhook Deliveries',
            'heading' => 'Webhook Delivery Queue',
            'kicker' => 'Operations',
            'description' => 'Riwayat delivery webhook internal ke aplikasi client lengkap dengan attempt, response, dan retry status.',
        ], [
            'deliveries' => $deliveries,
            'filters' => $filters,
            'applications' => Application::query()->orderBy('name')->pluck('name', 'code'),
            'providers' => PaymentProvider::query()->orderBy('name')->pluck('name', 'code'),
            'summary' => [
                'total_deliveries' => (int) ($summary->total_deliveries ?? 0),
                'successful_deliveries' => (int) ($summary->successful_deliveries ?? 0),
                'pending_deliveries' => (int) ($summary->pending_deliveries ?? 0),
                'failed_deliveries' => (int) ($summary->failed_deliveries ?? 0),
            ],
        ]);
    }
}
