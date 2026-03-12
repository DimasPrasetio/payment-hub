<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\Admin\ApplicationIndexRequest;
use App\Http\Requests\Admin\StoreApplicationRequest;
use App\Http\Requests\Admin\UpdateApplicationRequest;
use App\Models\Application;
use App\Models\PaymentProvider;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;

class ApplicationController extends AdminController
{
    public function index(ApplicationIndexRequest $request): View
    {
        $filters = $request->validated();

        $applications = Application::query()
            ->with('defaultProvider')
            ->withCount('paymentOrders')
            ->withCount([
                'paymentOrders as paid_orders_count' => fn ($query) => $query->where('status', 'PAID'),
                'webhookDeliveries as failed_webhook_deliveries_count' => fn ($query) => $query->where('status', 'failed'),
            ])
            ->withSum('paymentOrders as gross_amount', 'amount')
            ->withMax('paymentOrders as latest_payment_at', 'created_at')
            ->when($filters['q'] ?? null, function ($query, $search) {
                $query->where(function ($searchQuery) use ($search) {
                    $searchQuery
                        ->where('code', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%")
                        ->orWhere('webhook_url', 'like', "%{$search}%");
                });
            })
            ->when($filters['provider'] ?? null, fn ($query, $code) => $query->where('default_provider', $code))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status === 'active'))
            ->orderBy('name')
            ->paginate(12)
            ->withQueryString();

        return $this->renderPage('admin.applications.index', [
            'title' => 'Applications',
            'heading' => 'Application Management',
            'kicker' => 'Master Data',
            'description' => 'Aplikasi client yang memakai payment hub beserta default provider, volume transaksi, dan performa webhook.',
        ], [
            'applicationsList' => $applications,
            'filters' => $filters,
            'providers' => PaymentProvider::query()->orderBy('name')->pluck('name', 'code'),
        ]);
    }

    public function create(): View
    {
        return $this->renderPage('admin.applications.create', [
            'title' => 'Create Application',
            'heading' => 'Tambah Aplikasi Baru',
            'kicker' => 'Master Data',
            'description' => 'Daftarkan client app baru agar bisa menggunakan Payment Orchestrator dan dapatkan credential awalnya.',
        ], [
            'providers' => $this->selectableProviders(),
        ]);
    }

    public function store(StoreApplicationRequest $request): RedirectResponse
    {
        $credentials = $this->issueCredentials();

        $application = Application::query()->create([
            'code' => $request->string('code')->toString(),
            'name' => $request->string('name')->toString(),
            'api_key' => hash('sha256', $credentials['api_key']),
            'default_provider' => $request->string('default_provider')->toString(),
            'webhook_url' => $request->string('webhook_url')->toString(),
            'webhook_secret' => $credentials['webhook_secret'],
            'status' => $request->boolean('status', true),
        ]);

        return redirect()
            ->route('admin.applications.show', $application)
            ->with('success', 'Aplikasi baru berhasil dibuat.')
            ->with('issued_credentials', $credentials);
    }

    public function show(Application $application): View
    {
        $application->load([
            'defaultProvider',
            'paymentOrders' => fn ($query) => $query
                ->with(['application', 'latestProviderTransaction'])
                ->latest('created_at')
                ->limit(10),
            'webhookDeliveries' => fn ($query) => $query
                ->with(['application', 'paymentOrder'])
                ->latest('created_at')
                ->limit(10),
        ]);
        $application->loadCount([
            'paymentOrders',
            'paymentOrders as paid_orders_count' => fn ($query) => $query->where('status', 'PAID'),
            'webhookDeliveries as failed_webhook_deliveries_count' => fn ($query) => $query->where('status', 'failed'),
        ]);
        $application->loadSum('paymentOrders as gross_amount', 'amount');

        return $this->renderPage('admin.applications.show', [
            'title' => "Application {$application->code}",
            'heading' => $application->name,
            'kicker' => 'Application Detail',
            'description' => 'Detail konfigurasi aplikasi, transaksi terbaru, dan riwayat delivery webhook.',
        ], [
            'application' => $application,
            'providers' => $this->selectableProviders($application),
            'credentialStatus' => $this->credentialStatus($application),
        ]);
    }

    public function update(UpdateApplicationRequest $request, Application $application): RedirectResponse
    {
        $application->fill([
            'name' => $request->string('name')->toString(),
            'default_provider' => $request->string('default_provider')->toString(),
            'webhook_url' => $request->string('webhook_url')->toString(),
            'status' => $request->boolean('status'),
        ])->save();

        return redirect()
            ->route('admin.applications.show', $application)
            ->with('success', 'Konfigurasi aplikasi berhasil diperbarui.');
    }

    public function rotateApiKey(Application $application): RedirectResponse
    {
        $credentials = $this->issueCredentials();

        $application->forceFill([
            'api_key' => hash('sha256', $credentials['api_key']),
        ])->save();

        return redirect()
            ->route('admin.applications.show', $application)
            ->with('success', 'API key aplikasi berhasil digenerate ulang.')
            ->with('issued_credentials', [
                'api_key' => $credentials['api_key'],
            ]);
    }

    public function rotateWebhookSecret(Application $application): RedirectResponse
    {
        $credentials = $this->issueCredentials();

        $application->forceFill([
            'webhook_secret' => $credentials['webhook_secret'],
        ])->save();

        return redirect()
            ->route('admin.applications.show', $application)
            ->with('success', 'Webhook secret berhasil dirotasi.')
            ->with('issued_credentials', [
                'webhook_secret' => $credentials['webhook_secret'],
            ]);
    }

    public function destroy(Application $application): RedirectResponse
    {
        if ($application->paymentOrders()->exists() || $application->webhookDeliveries()->exists()) {
            return redirect()
                ->route('admin.applications.show', $application)
                ->with('error', 'Aplikasi tidak dapat dihapus karena sudah memiliki transaksi atau riwayat webhook.');
        }

        $application->delete();

        return redirect()
            ->route('admin.applications')
            ->with('success', 'Aplikasi berhasil dihapus.');
    }

    protected function issueCredentials(): array
    {
        return [
            'api_key' => $this->generateUniqueApiKey(),
            'webhook_secret' => 'whsec_' . Str::lower(Str::random(48)),
        ];
    }

    protected function generateUniqueApiKey(): string
    {
        do {
            $plainKey = 'app_' . Str::lower(Str::random(40));
            $hashedKey = hash('sha256', $plainKey);
        } while (Application::query()->where('api_key', $hashedKey)->exists());

        return $plainKey;
    }

    protected function credentialStatus(Application $application): array
    {
        return [
            'api_key_fingerprint' => strtoupper(substr((string) $application->getRawOriginal('api_key'), 0, 12)),
            'webhook_secret_length' => strlen((string) $application->webhook_secret),
        ];
    }

    protected function selectableProviders(?Application $application = null)
    {
        $providers = PaymentProvider::query()
            ->active()
            ->orderBy('name')
            ->pluck('name', 'code');

        if ($application !== null && $application->default_provider !== null && ! $providers->has($application->default_provider)) {
            $currentProvider = PaymentProvider::query()->where('code', $application->default_provider)->first();

            if ($currentProvider) {
                $providers->put($currentProvider->code, $currentProvider->name . ' (inactive)');
            }
        }

        return $providers;
    }
}
