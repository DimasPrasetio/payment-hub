@extends('layouts.admin')

@section('title', $pageTitle)

@php
    $providerFields = collect($providerProfile['fields']);
    $connectionFields = $providerFields->where('section', 'connection')->values();
    $endpointFields = $providerFields->where('section', 'endpoint')->values();
    $secretFields = $providerFields->filter(fn (array $field) => $field['sensitive'] ?? false)->values();
    $activationKeys = collect($providerProfile['activation_requirements'])->pluck('key')->flip();
    $configuredSecretKeys = collect($configuredSecrets)->keys()->flip();
    $ignoredConfig = collect($providerProfile['ignored_fields'])
        ->filter(function (array $field) use ($providerConfig) {
            $value = data_get($providerConfig, $field['key']);

            return filled($value) || $value === true;
        })
        ->values();
    $visibleSummaryFields = $connectionFields
        ->merge($endpointFields)
        ->filter(fn (array $field) => filled(data_get($providerConfig, $field['key'])))
        ->values();
    $extraConfig = collect($providerConfig)
        ->except(array_merge(App\Support\ProviderConsoleProfile::knownConfigKeys(), ['supports_refund_api']))
        ->all();
    $callbackUrl = route('api.callbacks.store', ['provider_code' => $provider->code]);
@endphp

@section('content')
    <section class="panel-card">
        <div class="panel-heading">
            <div class="flex flex-wrap items-center gap-3">
                <x-status-badge :label="$provider->is_active ? 'Aktif' : 'Tidak Aktif'" :tone="$provider->is_active ? 'emerald' : 'slate'" />
                <span class="provider-mode-pill">{{ $provider->sandbox_mode ? 'Sandbox' : 'Produksi' }}</span>
                @if ($providerProfile['supports_refund_toggle'])
                    <span class="provider-mode-pill provider-mode-pill-emerald">
                        {{ ! empty($providerConfig['supports_refund_api']) ? 'Refund API Aktif' : 'Refund API Nonaktif' }}
                    </span>
                @endif
            </div>

            <div class="provider-hero-actions">
                @foreach (collect($providerProfile['docs'])->take(2) as $doc)
                    <a href="{{ $doc['url'] }}" target="_blank" rel="noreferrer" class="provider-hero-link">
                        {{ $doc['label'] }}
                    </a>
                @endforeach
            </div>
        </div>
    </section>

    <section class="metric-grid">
        <x-metric-card label="Kode" :value="strtoupper($provider->code)" caption="" tone="slate" />
        <x-metric-card label="Metode Aktif" :value="$provider->active_payment_method_mappings_count . '/' . $provider->payment_method_mappings_count" caption="" tone="violet" />
        <x-metric-card label="Total Transaksi" :value="number_format($provider->payment_orders_count)" caption="" tone="cyan" />
        <x-metric-card label="Tingkat Keberhasilan" :value="$provider->payment_orders_count > 0 ? number_format(($provider->paid_payment_orders_count / $provider->payment_orders_count) * 100, 1) . '%' : '0.0%'" caption="" tone="emerald" />
    </section>

    <section class="provider-workspace">
        <div class="provider-main-stack">
            <article class="panel-card">
                <div class="panel-heading">
                    <div>
                        <p class="section-kicker">Pengelolaan Integrasi</p>
                        <h3 class="section-title">Edit konfigurasi {{ $providerProfile['label'] }}</h3>
                    </div>
                </div>

                <form method="POST" action="{{ route('admin.providers.update', $provider) }}" class="mt-6 space-y-5">
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="code" value="{{ $provider->code }}">

                    <section class="provider-form-section">
                        <div class="provider-form-section-head">
                            <p class="section-kicker">Profil</p>
                            <h4 class="section-title">Identitas dan parameter utama</h4>
                        </div>

                        <div class="provider-form-grid">
                            <div class="provider-field-card">
                                <div class="provider-field-head">
                                    <label for="name" class="form-label">Nama Provider</label>
                                </div>
                                <input id="name" name="name" class="form-input" value="{{ old('name', $provider->name) }}"
                                    placeholder="{{ $providerProfile['label'] }}">
                                <p class="field-help">Nama tampilan yang muncul di panel admin.</p>
                                @error('name')
                                    <p class="field-error">{{ $message }}</p>
                                @enderror
                            </div>

                            @foreach ($connectionFields as $field)
                                <div class="provider-field-card">
                                    <div class="provider-field-head">
                                        <label for="{{ $field['key'] }}" class="form-label">{{ $field['label'] }}</label>
                                        @if ($activationKeys->has($field['key']))
                                            <span class="provider-field-note provider-field-note-required">Wajib</span>
                                        @endif
                                    </div>
                                    <input id="{{ $field['key'] }}" name="{{ $field['key'] }}"
                                        type="{{ $field['type'] === 'url' ? 'url' : 'text' }}" class="form-input"
                                        value="{{ old($field['key'], data_get($providerConfig, $field['key'])) }}"
                                        placeholder="{{ $field['placeholder'] }}">
                                    @if ($field['help'])
                                        <p class="field-help">{{ $field['help'] }}</p>
                                    @endif
                                    @error($field['key'])
                                        <p class="field-error">{{ $message }}</p>
                                    @enderror
                                </div>
                            @endforeach
                        </div>
                    </section>

                    @if ($endpointFields->isNotEmpty())
                        <section class="provider-form-section">
                            <div class="provider-form-section-head">
                                <p class="section-kicker">Endpoint</p>
                                <h4 class="section-title">Routing, callback, dan redirect</h4>
                            </div>

                            <div class="provider-form-grid">
                                @foreach ($endpointFields as $field)
                                    <div class="provider-field-card">
                                        <div class="provider-field-head">
                                            <label for="{{ $field['key'] }}" class="form-label">{{ $field['label'] }}</label>
                                            @if ($activationKeys->has($field['key']))
                                                <span class="provider-field-note provider-field-note-required">Wajib</span>
                                            @endif
                                        </div>
                                        <input id="{{ $field['key'] }}" name="{{ $field['key'] }}"
                                            type="url" class="form-input"
                                            value="{{ old($field['key'], data_get($providerConfig, $field['key'])) }}"
                                            placeholder="{{ $field['placeholder'] }}">
                                        @if ($field['help'])
                                            <p class="field-help">{{ $field['help'] }}</p>
                                        @endif
                                        @error($field['key'])
                                            <p class="field-error">{{ $message }}</p>
                                        @enderror
                                    </div>
                                @endforeach
                            </div>
                        </section>
                    @endif

                    @if ($secretFields->isNotEmpty())
                        <section class="provider-form-section">
                            <div class="provider-form-section-head">
                                <p class="section-kicker">Credential Rahasia</p>
                                <h4 class="section-title">Secret yang dipakai adapter</h4>
                            </div>

                            <div class="provider-form-grid">
                                @foreach ($secretFields as $field)
                                    <div class="provider-field-card">
                                        <div class="provider-field-head">
                                            <label for="{{ $field['key'] }}" class="form-label">{{ $field['label'] }}</label>
                                            @if ($configuredSecretKeys->has($field['key']))
                                                <span class="provider-field-note provider-field-note-ready">Tersimpan</span>
                                            @elseif ($activationKeys->has($field['key']))
                                                <span class="provider-field-note provider-field-note-required">Wajib</span>
                                            @endif
                                        </div>
                                        <input id="{{ $field['key'] }}" name="{{ $field['key'] }}" type="password"
                                            class="form-input" value="" placeholder="{{ $field['placeholder'] }}">
                                        @if ($field['help'])
                                            <p class="field-help">{{ $field['help'] }}</p>
                                        @endif
                                        @error($field['key'])
                                            <p class="field-error">{{ $message }}</p>
                                        @enderror
                                    </div>
                                @endforeach
                            </div>
                        </section>
                    @endif

                    <section class="provider-form-section">
                        <div class="provider-form-section-head">
                            <p class="section-kicker">Operasional</p>
                            <h4 class="section-title">Mode dan capability</h4>
                        </div>

                        <div class="provider-toggle-grid">
                            <label class="provider-toggle-card">
                                <input type="hidden" name="is_active" value="0">
                                <input class="checkbox-input" type="checkbox" name="is_active" value="1"
                                    @checked(old('is_active', $provider->is_active))>
                                <span>
                                    <span class="checkbox-title">Provider aktif</span>
                                    <span class="checkbox-copy">Provider akan tersedia untuk pemilihan di client API.</span>
                                </span>
                            </label>

                            <label class="provider-toggle-card">
                                <input type="hidden" name="sandbox_mode" value="0">
                                <input class="checkbox-input" type="checkbox" name="sandbox_mode" value="1"
                                    @checked(old('sandbox_mode', $provider->sandbox_mode))>
                                <span>
                                    <span class="checkbox-title">Gunakan mode sandbox</span>
                                    <span class="checkbox-copy">Adapter akan memakai endpoint sandbox atau test mode jika provider mendukung.</span>
                                </span>
                            </label>

                            @if ($providerProfile['supports_refund_toggle'])
                                <label class="provider-toggle-card">
                                    <input type="hidden" name="supports_refund_api" value="0">
                                    <input class="checkbox-input" type="checkbox" name="supports_refund_api" value="1"
                                        @checked(old('supports_refund_api', ! empty($providerConfig['supports_refund_api'])))>
                                    <span>
                                        <span class="checkbox-title">Refund via API didukung</span>
                                        <span class="checkbox-copy">Aktifkan hanya jika akun dan produk {{ $providerProfile['label'] }} memang mendukung refund API.</span>
                                    </span>
                                </label>
                            @endif
                        </div>
                    </section>

                    <details class="disclosure-card">
                        <summary class="disclosure-summary">
                            <div>
                                <p class="disclosure-title">Konfigurasi lanjutan</p>
                                <p class="disclosure-meta">Gunakan hanya jika provider memerlukan key tambahan di luar field utama.</p>
                            </div>
                            <span class="disclosure-indicator">Lihat</span>
                        </summary>
                        <div class="disclosure-body">
                            <div class="provider-field-card provider-field-card-full">
                                <label for="extra_config" class="form-label">Extra Config JSON</label>
                                <textarea id="extra_config" name="extra_config" class="form-textarea"
                                    placeholder='{"issuer":"BCA","channel_category":"virtual_account"}'>{{ old('extra_config', ! empty($extraConfig) ? json_encode($extraConfig, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : '') }}</textarea>
                                <p class="field-help">Key yang sudah punya field khusus akan diabaikan jika dimasukkan lagi di sini.</p>
                                @error('extra_config')
                                    <p class="field-error">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                    </details>

                    <div class="form-actions">
                        <button type="submit" class="button-primary">Simpan Perubahan</button>
                        <a href="{{ route('admin.providers') }}" class="button-link">Kembali ke daftar provider</a>
                    </div>
                </form>
            </article>

            <article class="panel-card">
                <div class="panel-heading">
                    <div>
                        <p class="section-kicker">Metode Pembayaran</p>
                        <h3 class="section-title">Daftar layanan yang tersedia</h3>
                    </div>
                </div>

                <div class="stack-list mt-6">
                    @forelse ($provider->paymentMethodMappings as $mapping)
                        <div class="stack-item">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <p class="stack-title">{{ $mapping->display_name }}</p>
                                    <p class="stack-meta">{{ $mapping->internal_code }} &middot; {{ $mapping->provider_method_code }}</p>
                                </div>
                                <x-status-badge :label="$mapping->is_active ? 'Aktif' : 'Tidak Aktif'" :tone="$mapping->is_active ? 'emerald' : 'slate'" />
                            </div>
                            <p class="table-copy mt-3">
                                Biaya Tetap IDR {{ number_format($mapping->fee_flat, 0, ',', '.') }} &middot; Biaya %
                                {{ number_format((float) $mapping->fee_percent, 2) }}
                            </p>
                        </div>
                    @empty
                        <div class="empty-state">Belum ada layanan yang tersambung ke saluran ini.</div>
                    @endforelse
                </div>
            </article>

            <article class="panel-card">
                <div class="panel-heading">
                    <div>
                        <p class="section-kicker">Daftar Transaksi</p>
                        <h3 class="section-title">Pesanan pembayaran terbaru</h3>
                    </div>
                </div>

                <div class="data-table mt-6">
                    <div class="table-head">
                        <span>Pembayaran</span>
                        <span>Pelanggan</span>
                        <span>Saluran</span>
                        <span>Jumlah</span>
                        <span>Status</span>
                    </div>
                    @forelse ($provider->paymentOrders as $payment)
                        <x-payment-row :payment="$payment" />
                    @empty
                        <div class="empty-state">Belum ada transaksi di saluran ini.</div>
                    @endforelse
                </div>
            </article>
        </div>

        <aside class="provider-sidebar-stack">
            <article class="panel-card">
                <div class="panel-heading">
                    <div>
                        <p class="section-kicker">Ringkasan Operasional</p>
                        <h3 class="section-title">Konfigurasi yang aktif dipakai</h3>
                    </div>
                </div>

                <div class="provider-aside-list mt-6">
                    <div class="provider-aside-item">
                        <div>
                            <p class="provider-aside-label">Callback Endpoint</p>
                            <p class="provider-aside-code">{{ $callbackUrl }}</p>
                        </div>
                    </div>

                    <div class="provider-aside-item">
                        <div>
                            <p class="provider-aside-label">Lingkungan</p>
                            <p class="provider-aside-value">{{ $provider->sandbox_mode ? 'Uji Coba (Sandbox)' : 'Produksi' }}</p>
                        </div>
                    </div>

                    @foreach ($visibleSummaryFields as $field)
                        <div class="provider-aside-item">
                            <div class="min-w-0">
                                <p class="provider-aside-label">{{ $field['label'] }}</p>
                                <p class="{{ $field['type'] === 'url' ? 'provider-aside-code' : 'provider-aside-value' }}">{{ data_get($providerConfig, $field['key']) }}</p>
                            </div>
                        </div>
                    @endforeach

                    @if ($providerProfile['supports_refund_toggle'])
                        <div class="provider-aside-item">
                            <div>
                                <p class="provider-aside-label">Refund via API</p>
                                <p class="provider-aside-value">{{ ! empty($providerConfig['supports_refund_api']) ? 'Didukung' : 'Belum didukung' }}</p>
                            </div>
                        </div>
                    @endif
                </div>
            </article>

            <article class="panel-card">
                <div class="panel-heading">
                    <div>
                        <p class="section-kicker">Kebutuhan Aktivasi</p>
                        <h3 class="section-title">Checklist minimum</h3>
                    </div>
                </div>

                <div class="stack-list mt-6">
                    @forelse ($providerProfile['activation_requirements'] as $field)
                        <div class="stack-item">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <p class="stack-title">{{ $field['label'] }}</p>
                                    <p class="stack-meta">{{ strtoupper(str_replace('_', ' ', $field['key'])) }}</p>
                                </div>
                                <x-status-badge :label="filled($providerConfig[$field['key']] ?? null) ? 'Siap' : 'Belum'" :tone="filled($providerConfig[$field['key']] ?? null) ? 'emerald' : 'amber'" />
                            </div>
                        </div>
                    @empty
                        <div class="empty-state">Provider ini belum memiliki checklist aktivasi bawaan.</div>
                    @endforelse
                </div>
            </article>

            <details class="disclosure-card">
                <summary class="disclosure-summary">
                    <div>
                        <p class="disclosure-title">Informasi sekunder</p>
                        <p class="disclosure-meta">Dokumentasi, status secret, aplikasi terhubung, dan kompatibilitas.</p>
                    </div>
                    <span class="disclosure-indicator">Lihat</span>
                </summary>
                <div class="disclosure-body space-y-6">
                    <div>
                        <p class="section-kicker">Dokumentasi Resmi</p>
                        <div class="provider-bullet-list mt-4">
                            @forelse ($providerProfile['highlights'] as $highlight)
                                <p class="provider-bullet-item">{{ $highlight }}</p>
                            @empty
                                <div class="empty-state">Belum ada ringkasan integrasi khusus untuk provider ini.</div>
                            @endforelse
                        </div>

                        <div class="provider-doc-list mt-4">
                            @forelse ($providerProfile['docs'] as $doc)
                                <div class="provider-doc-item">
                                    <p class="stack-title">{{ $doc['label'] }}</p>
                                    <p class="stack-meta">{{ $doc['caption'] }}</p>
                                    <a href="{{ $doc['url'] }}" target="_blank" rel="noreferrer" class="button-link mt-4">Buka Dokumen</a>
                                </div>
                            @empty
                                <div class="empty-state">Belum ada tautan dokumentasi resmi yang dipetakan untuk provider ini.</div>
                            @endforelse
                        </div>
                    </div>

                    <div>
                        <p class="section-kicker">Credential Tersimpan</p>
                        <div class="stack-list mt-4">
                            @forelse ($configuredSecrets as $key => $label)
                                <div class="stack-item">
                                    <p class="stack-title">{{ $label }}</p>
                                    <p class="stack-meta">{{ strtoupper(str_replace('_', ' ', $key)) }} sudah tersimpan terenkripsi.</p>
                                </div>
                            @empty
                                <div class="empty-state">Belum ada credential sensitif yang tersimpan untuk provider ini.</div>
                            @endforelse
                        </div>
                    </div>

                    @if ($ignoredConfig->isNotEmpty())
                        <div>
                            <p class="section-kicker">Kompatibilitas</p>
                            <div class="stack-list mt-4">
                                @foreach ($ignoredConfig as $field)
                                    @php
                                        $value = data_get($providerConfig, $field['key']);
                                    @endphp
                                    <div class="stack-item">
                                        <div class="flex items-start justify-between gap-3">
                                            <div>
                                                <p class="stack-title">{{ $field['label'] }}</p>
                                                <p class="stack-meta">
                                                    @if ($field['sensitive'] ?? false)
                                                        Nilai lama masih tersimpan terenkripsi, tetapi tidak dipakai oleh adapter {{ $provider->name }} saat ini.
                                                    @else
                                                        {{ is_bool($value) ? ($value ? 'Enabled' : 'Disabled') : $value }}
                                                    @endif
                                                </p>
                                            </div>
                                            <x-status-badge label="Diabaikan" tone="slate" />
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    <div>
                        <p class="section-kicker">Aplikasi Terhubung</p>
                        <div class="stack-list mt-4">
                            @forelse ($provider->applications as $application)
                                <div class="stack-item">
                                    <p class="stack-title">{{ $application->name }}</p>
                                    <p class="stack-meta">{{ $application->code }} &middot; {{ $application->status ? 'Aktif' : 'Tidak Aktif' }}</p>
                                </div>
                            @empty
                                <div class="empty-state">Belum ada aplikasi yang terhubung langsung ke provider ini.</div>
                            @endforelse
                        </div>
                    </div>
                </div>
            </details>
        </aside>
    </section>
@endsection
