<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\Admin\PaymentMethodIndexRequest;
use App\Models\PaymentMethodMapping;
use App\Models\PaymentProvider;
use Illuminate\Contracts\View\View;

class PaymentMethodMappingController extends AdminController
{
    public function index(PaymentMethodIndexRequest $request): View
    {
        $filters = $request->validated();

        $mappings = PaymentMethodMapping::query()
            ->with('paymentProvider')
            ->when($filters['q'] ?? null, function ($query, $search) {
                $query->where(function ($searchQuery) use ($search) {
                    $searchQuery
                        ->where('internal_code', 'like', "%{$search}%")
                        ->orWhere('provider_method_code', 'like', "%{$search}%")
                        ->orWhere('display_name', 'like', "%{$search}%");
                });
            })
            ->when($filters['provider'] ?? null, fn ($query, $code) => $query->where('provider_code', $code))
            ->when($filters['group'] ?? null, fn ($query, $group) => $query->where('group', $group))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('is_active', $status === 'active'))
            ->orderBy('provider_code')
            ->orderBy('display_name')
            ->paginate(20)
            ->withQueryString();

        return $this->renderPage('admin.payment-methods.index', [
            'title' => 'Payment Methods',
            'heading' => 'Payment Method Mapping',
            'kicker' => 'Master Data',
            'description' => 'Mapping internal payment method ke kode provider agar public API tetap konsisten walau provider bertambah.',
        ], [
            'mappings' => $mappings,
            'filters' => $filters,
            'providers' => PaymentProvider::query()->orderBy('name')->pluck('name', 'code'),
            'groups' => PaymentMethodMapping::query()->whereNotNull('group')->distinct()->orderBy('group')->pluck('group'),
        ]);
    }
}
