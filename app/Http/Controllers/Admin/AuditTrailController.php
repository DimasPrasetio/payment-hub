<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\Admin\PaymentEventIndexRequest;
use App\Models\Application;
use App\Models\PaymentEvent;
use App\Models\PaymentProvider;
use Illuminate\Contracts\View\View;

class AuditTrailController extends AdminController
{
    public function index(PaymentEventIndexRequest $request): View
    {
        $filters = $request->validated();

        $events = PaymentEvent::query()
            ->with('paymentOrder.application')
            ->when($filters['q'] ?? null, fn ($query, $search) => $query->where('event_type', 'like', "%{$search}%"))
            ->when($filters['application'] ?? null, fn ($query, $code) => $query->whereHas('paymentOrder.application', fn ($applicationQuery) => $applicationQuery->where('code', $code)))
            ->when($filters['provider'] ?? null, fn ($query, $code) => $query->whereHas('paymentOrder', fn ($orderQuery) => $orderQuery->where('provider_code', $code)))
            ->when($filters['payment'] ?? null, fn ($query, $paymentId) => $query->whereHas('paymentOrder', fn ($orderQuery) => $orderQuery->where('public_id', $paymentId)))
            ->when($filters['event_type'] ?? null, fn ($query, $eventType) => $query->where('event_type', $eventType))
            ->when($filters['date_from'] ?? null, fn ($query, $date) => $query->whereDate('created_at', '>=', $date))
            ->when($filters['date_to'] ?? null, fn ($query, $date) => $query->whereDate('created_at', '<=', $date))
            ->latest('created_at')
            ->paginate(20)
            ->withQueryString();

        return $this->renderPage('admin.audit-trail.index', [
            'title' => 'Audit Trail',
            'heading' => 'Payment Event Timeline',
            'kicker' => 'Operations',
            'description' => 'Timeline event untuk melacak create, provider request-response, callback, dan webhook dalam satu alur audit.',
        ], [
            'events' => $events,
            'filters' => $filters,
            'applications' => Application::query()->orderBy('name')->pluck('name', 'code'),
            'providers' => PaymentProvider::query()->orderBy('name')->pluck('name', 'code'),
        ]);
    }
}
