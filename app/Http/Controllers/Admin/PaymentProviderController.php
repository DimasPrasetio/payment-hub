<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\Admin\ProviderIndexRequest;
use App\Http\Requests\Admin\UpdateProviderRequest;
use App\Models\PaymentProvider;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class PaymentProviderController extends AdminController
{
    public function index(ProviderIndexRequest $request): View
    {
        $filters = $request->validated();

        $providers = PaymentProvider::query()
            ->withCount('paymentMethodMappings')
            ->withCount([
                'paymentMethodMappings as active_payment_method_mappings_count' => fn ($query) => $query->where('is_active', true),
                'paymentOrders',
                'paymentOrders as paid_payment_orders_count' => fn ($query) => $query->where('status', 'PAID'),
            ])
            ->withSum('paymentOrders as gross_amount', 'amount')
            ->when($filters['q'] ?? null, function ($query, $search) {
                $query->where(function ($searchQuery) use ($search) {
                    $searchQuery
                        ->where('code', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%");
                });
            })
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('is_active', $status === 'active'))
            ->when($filters['mode'] ?? null, fn ($query, $mode) => $query->where('sandbox_mode', $mode === 'sandbox'))
            ->orderBy('name')
            ->paginate(12)
            ->withQueryString();

        return $this->renderPage('admin.providers.index', [
            'title' => 'Providers',
            'heading' => 'Provider Management',
            'kicker' => 'Master Data',
            'description' => 'Daftar provider pembayaran dengan status, mode operasional, volume order, dan cakupan method mapping.',
        ], [
            'providersList' => $providers,
            'filters' => $filters,
        ]);
    }

    public function show(PaymentProvider $provider): View
    {
        $provider->load([
            'applications' => fn ($query) => $query->orderBy('name'),
            'paymentMethodMappings' => fn ($query) => $query->orderBy('display_name'),
            'paymentOrders' => fn ($query) => $query
                ->with(['application', 'latestProviderTransaction'])
                ->latest('created_at')
                ->limit(10),
        ]);
        $provider->loadCount([
            'paymentOrders',
            'paymentOrders as paid_payment_orders_count' => fn ($query) => $query->where('status', 'PAID'),
            'paymentMethodMappings',
            'paymentMethodMappings as active_payment_method_mappings_count' => fn ($query) => $query->where('is_active', true),
        ]);
        $provider->loadSum('paymentOrders as gross_amount', 'amount');

        return $this->renderPage('admin.providers.show', [
            'title' => "Provider {$provider->code}",
            'heading' => $provider->name,
            'kicker' => 'Provider Detail',
            'description' => 'Ringkasan konfigurasi provider, method mapping, aplikasi yang terhubung, dan transaksi terbaru.',
        ], [
            'provider' => $provider,
            'providerConfig' => $this->providerConfig($provider),
            'configuredSecrets' => $this->configuredSecrets($provider),
        ]);
    }

    public function update(UpdateProviderRequest $request, PaymentProvider $provider): RedirectResponse
    {
        $validated = $request->validated();

        $provider->fill([
            'name' => $validated['name'],
            'is_active' => $request->boolean('is_active'),
            'sandbox_mode' => $request->boolean('sandbox_mode'),
            'config' => $this->mergeProviderConfig($provider, $validated),
        ])->save();

        return redirect()
            ->route('admin.providers.show', $provider)
            ->with('success', 'Konfigurasi provider berhasil diperbarui.');
    }

    protected function providerConfig(PaymentProvider $provider): array
    {
        return array_merge([
            'merchant_code' => null,
            'api_key' => null,
            'private_key' => null,
            'client_key' => null,
            'server_key' => null,
            'secret_key' => null,
            'callback_token' => null,
            'api_base_url' => null,
            'public_base_url' => null,
            'return_url' => null,
            'supports_refund_api' => false,
        ], $provider->config ?? []);
    }

    protected function configuredSecrets(PaymentProvider $provider): array
    {
        $config = $provider->config ?? [];

        return collect([
            'api_key' => 'API Key',
            'private_key' => 'Private Key',
            'client_key' => 'Client Key',
            'server_key' => 'Server Key',
            'secret_key' => 'Secret Key',
            'callback_token' => 'Callback Token',
        ])->filter(fn (string $label, string $key) => filled($config[$key] ?? null))
            ->all();
    }

    protected function mergeProviderConfig(PaymentProvider $provider, array $validated): array
    {
        $current = $provider->config ?? [];
        $config = $current;

        foreach (['merchant_code', 'api_base_url', 'public_base_url', 'return_url'] as $field) {
            $value = trim((string) ($validated[$field] ?? ''));

            if ($value === '') {
                unset($config[$field]);
                continue;
            }

            $config[$field] = $value;
        }

        foreach (['api_key', 'private_key', 'client_key', 'server_key', 'secret_key', 'callback_token'] as $field) {
            $value = trim((string) ($validated[$field] ?? ''));

            if ($value !== '') {
                $config[$field] = $value;
            }
        }

        $supportsRefundApi = array_key_exists('supports_refund_api', $validated)
            ? filter_var($validated['supports_refund_api'], FILTER_VALIDATE_BOOL)
            : false;
        $config['supports_refund_api'] = $supportsRefundApi;

        $extraConfig = trim((string) ($validated['extra_config'] ?? ''));

        if ($extraConfig !== '') {
            $decoded = json_decode($extraConfig, true);

            if (is_array($decoded)) {
                $config = array_merge($config, $decoded);
            }
        }

        return $config;
    }
}
