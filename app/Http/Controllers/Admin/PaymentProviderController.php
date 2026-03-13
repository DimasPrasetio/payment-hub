<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\Admin\ProviderIndexRequest;
use App\Http\Requests\Admin\UpdateProviderRequest;
use App\Models\PaymentProvider;
use App\Support\ProviderConsoleProfile;
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
            'providerProfile' => ProviderConsoleProfile::for($provider->code),
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
        return array_merge(ProviderConsoleProfile::defaults(), $provider->config ?? []);
    }

    protected function configuredSecrets(PaymentProvider $provider): array
    {
        $config = $provider->config ?? [];
        $profile = ProviderConsoleProfile::for($provider->code);

        return collect($profile['fields'])
            ->filter(fn (array $field) => ($field['sensitive'] ?? false) && filled($config[$field['key']] ?? null))
            ->mapWithKeys(fn (array $field) => [$field['key'] => $field['label']])
            ->all();
    }

    protected function mergeProviderConfig(PaymentProvider $provider, array $validated): array
    {
        $current = $provider->config ?? [];
        $config = $current;
        $profile = ProviderConsoleProfile::for($provider->code);
        $fields = collect($profile['fields']);
        $plainFields = $fields->reject(fn (array $field) => $field['sensitive'] ?? false);
        $secretFields = $fields->filter(fn (array $field) => $field['sensitive'] ?? false);

        foreach ($plainFields as $field) {
            $key = $field['key'];

            if (! array_key_exists($key, $validated)) {
                continue;
            }

            $value = trim((string) ($validated[$key] ?? ''));

            if ($value === '') {
                unset($config[$key]);
                continue;
            }

            $config[$key] = $value;
        }

        foreach ($secretFields as $field) {
            $key = $field['key'];

            if (! array_key_exists($key, $validated)) {
                continue;
            }

            $value = trim((string) ($validated[$key] ?? ''));

            if ($value !== '') {
                $config[$key] = $value;
            }
        }

        if ($profile['supports_refund_toggle']) {
            $supportsRefundApi = array_key_exists('supports_refund_api', $validated)
                ? filter_var($validated['supports_refund_api'], FILTER_VALIDATE_BOOL)
                : false;
            $config['supports_refund_api'] = $supportsRefundApi;
        } else {
            unset($config['supports_refund_api']);
        }

        $extraConfig = trim((string) ($validated['extra_config'] ?? ''));

        if ($extraConfig !== '') {
            $decoded = json_decode($extraConfig, true);

            if (is_array($decoded)) {
                $decoded = array_diff_key($decoded, array_flip(array_merge(
                    ProviderConsoleProfile::knownConfigKeys(),
                    ['supports_refund_api'],
                )));
                $config = array_merge($config, $decoded);
            }
        }

        return $config;
    }
}
