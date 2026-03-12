<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\Admin\StatisticsIndexRequest;
use App\Models\Application;
use App\Models\PaymentOrder;
use App\Models\PaymentProvider;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;

class StatisticsController extends AdminController
{
    public function index(StatisticsIndexRequest $request): View
    {
        $filters = $request->validated();

        $baseQuery = PaymentOrder::query()
            ->when($filters['application'] ?? null, fn ($query, $code) => $query->whereHas('application', fn ($applicationQuery) => $applicationQuery->where('code', $code)))
            ->when($filters['provider'] ?? null, fn ($query, $code) => $query->where('provider_code', $code))
            ->when($filters['date_from'] ?? null, fn ($query, $date) => $query->whereDate('created_at', '>=', $date))
            ->when($filters['date_to'] ?? null, fn ($query, $date) => $query->whereDate('created_at', '<=', $date));

        $summary = (clone $baseQuery)
            ->selectRaw("
                COUNT(*) as total_orders,
                COALESCE(SUM(amount), 0) as gross_amount,
                SUM(CASE WHEN status = 'PAID' THEN 1 ELSE 0 END) as paid_orders,
                SUM(CASE WHEN status IN ('FAILED', 'EXPIRED') THEN 1 ELSE 0 END) as problem_orders
            ")
            ->first();

        $statusBreakdown = (clone $baseQuery)
            ->toBase()
            ->select(
                'status',
                DB::raw('COUNT(*) as total_orders'),
                DB::raw('COALESCE(SUM(amount), 0) as gross_amount')
            )
            ->groupBy('status')
            ->orderByDesc('total_orders')
            ->get();

        $providerBreakdown = (clone $baseQuery)
            ->toBase()
            ->select(
                'provider_code',
                DB::raw('COUNT(*) as total_orders'),
                DB::raw('COALESCE(SUM(amount), 0) as gross_amount')
            )
            ->groupBy('provider_code')
            ->orderByDesc('total_orders')
            ->get();

        $applicationBreakdown = (clone $baseQuery)
            ->join('applications', 'applications.id', '=', 'payment_orders.application_id')
            ->toBase()
            ->select(
                'applications.code',
                'applications.name',
                DB::raw('COUNT(payment_orders.id) as total_orders'),
                DB::raw('COALESCE(SUM(payment_orders.amount), 0) as gross_amount')
            )
            ->groupBy('applications.code', 'applications.name')
            ->orderByDesc('total_orders')
            ->get();

        $dailyVolume = (clone $baseQuery)
            ->toBase()
            ->select(
                DB::raw('DATE(created_at) as day'),
                DB::raw('COUNT(*) as total_orders'),
                DB::raw('COALESCE(SUM(amount), 0) as gross_amount')
            )
            ->groupBy(DB::raw('DATE(created_at)'))
            ->orderBy('day')
            ->get();

        return $this->renderPage('admin.statistics.index', [
            'title' => 'Statistics',
            'heading' => 'Health & Transaction Statistics',
            'kicker' => 'Control Center',
            'description' => 'Statistik transaksi, breakdown status, distribusi provider, dan tren volume berdasarkan filter periode.',
        ], [
            'filters' => $filters,
            'providers' => PaymentProvider::query()->orderBy('name')->pluck('name', 'code'),
            'applications' => Application::query()->orderBy('name')->pluck('name', 'code'),
            'summary' => [
                'total_orders' => (int) ($summary->total_orders ?? 0),
                'gross_amount' => (int) ($summary->gross_amount ?? 0),
                'paid_orders' => (int) ($summary->paid_orders ?? 0),
                'problem_orders' => (int) ($summary->problem_orders ?? 0),
            ],
            'statusBreakdown' => $statusBreakdown,
            'providerBreakdown' => $providerBreakdown,
            'applicationBreakdown' => $applicationBreakdown,
            'dailyVolume' => $dailyVolume,
        ]);
    }
}
