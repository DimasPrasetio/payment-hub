@extends('layouts.admin')

@section('title', $pageTitle)

@php
    $knownConfigKeys = [
        'merchant_code',
        'api_key',
        'private_key',
        'client_key',
        'server_key',
        'secret_key',
        'callback_token',
        'api_base_url',
        'public_base_url',
        'return_url',
        'supports_refund_api',
    ];

    $extraConfig = collect($providerConfig)->except($knownConfigKeys)->all();
    $providerRequirements = match ($provider->code) {
        'tripay' => [
            'merchant_code' => 'Merchant Code',
            'api_key' => 'API Key',
            'private_key' => 'Private Key',
        ],
        'midtrans' => [
            'server_key' => 'Server Key',
            'client_key' => 'Client Key (opsional untuk redirect-only)',
        ],
        'xendit' => [
            'secret_key' => 'Secret Key',
            'callback_token' => 'Callback Token',
        ],
        default => [],
    };
@endphp

@section('content')
    <section class="metric-grid">
        <x-metric-card label="Total Transaksi" :value="number_format($provider->payment_orders_count)"
            caption="Jumlah transaksi masuk" tone="cyan" />
        <x-metric-card label="Total Nominal" :value="'IDR ' . number_format($provider->gross_amount ?? 0, 0, ',', '.')"
            caption="Nilai akumulasi transaksi" tone="emerald" />
        <x-metric-card label="Metode Aktif" :value="$provider->active_payment_method_mappings_count . '/' . $provider->payment_method_mappings_count"
            caption="Jalur yang terbuka" tone="violet" />
        <x-metric-card label="Tingkat Keberhasilan" :value="$provider->payment_orders_count > 0 ? number_format(($provider->paid_payment_orders_count / $provider->payment_orders_count) * 100, 1) . '%' : '0.0%'"
            caption="Rasio transaksi sukses" tone="amber" />
    </section>

    <section class="detail-grid">
        <article class="panel-card">
            <div class="panel-heading">
                <div>
                    <p class="section-kicker">Saluran Pembayaran</p>
                    <h3 class="section-title">Konfigurasi Inti</h3>
                </div>
                <x-status-badge :label="$provider->is_active ? 'Aktif' : 'Tidak Aktif'" :tone="$provider->is_active ? 'emerald' : 'slate'" />
            </div>

            <dl class="description-list">
                <div class="description-item">
                    <dt>Kode Saluran</dt>
                    <dd>{{ $provider->code }}</dd>
                </div>
                <div class="description-item">
                    <dt>Nama Saluran</dt>
                    <dd>{{ $provider->name }}</dd>
                </div>
                <div class="description-item">
                    <dt>Lingkungan</dt>
                    <dd>{{ $provider->sandbox_mode ? 'Uji Coba (Sandbox)' : 'Produksi' }}</dd>
                </div>
                <div class="description-item">
                    <dt>Callback URL</dt>
                    <dd>{{ route('api.callbacks.store', ['provider_code' => $provider->code]) }}</dd>
                </div>
                <div class="description-item">
                    <dt>API Base URL</dt>
                    <dd>{{ $providerConfig['api_base_url'] ?: '-' }}</dd>
                </div>
                <div class="description-item">
                    <dt>Public Base URL</dt>
                    <dd>{{ $providerConfig['public_base_url'] ?: '-' }}</dd>
                </div>
                <div class="description-item">
                    <dt>Merchant Code</dt>
                    <dd>{{ $providerConfig['merchant_code'] ?: '-' }}</dd>
                </div>
                <div class="description-item">
                    <dt>Refund API</dt>
                    <dd>{{ ! empty($providerConfig['supports_refund_api']) ? 'Didukung' : 'Belum didukung' }}</dd>
                </div>
            </dl>
        </article>

        <article class="panel-card">
            <div class="panel-heading">
                <div>
                    <p class="section-kicker">Credential Tersimpan</p>
                    <h3 class="section-title">Status Secret</h3>
                </div>
            </div>

            <div class="stack-list">
                @forelse ($configuredSecrets as $key => $label)
                    <div class="stack-item">
                        <p class="stack-title">{{ $label }}</p>
                        <p class="stack-meta">{{ strtoupper(str_replace('_', ' ', $key)) }} sudah tersimpan terenkripsi.</p>
                    </div>
                @empty
                    <div class="empty-state">Belum ada credential sensitif yang tersimpan untuk provider ini.</div>
                @endforelse
            </div>
        </article>
    </section>

    <section class="panel-card">
        <div class="panel-heading">
            <div>
                <p class="section-kicker">Kebutuhan Aktivasi</p>
                <h3 class="section-title">Field minimum sebelum provider aktif</h3>
            </div>
        </div>

        <div class="stack-list mt-6">
            @forelse ($providerRequirements as $key => $label)
                <div class="stack-item">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="stack-title">{{ $label }}</p>
                            <p class="stack-meta">{{ strtoupper(str_replace('_', ' ', $key)) }}</p>
                        </div>
                        <x-status-badge :label="filled($providerConfig[$key] ?? null) ? 'Siap' : 'Belum'" :tone="filled($providerConfig[$key] ?? null) ? 'emerald' : 'amber'" />
                    </div>
                </div>
            @empty
                <div class="empty-state">Provider ini belum memiliki checklist aktivasi bawaan.</div>
            @endforelse
        </div>
    </section>

    <section class="panel-card">
        <div class="panel-heading">
            <div>
                <p class="section-kicker">Pengelolaan Integrasi</p>
                <h3 class="section-title">Edit Credential dan Endpoint Provider</h3>
            </div>
        </div>

        <form method="POST" action="{{ route('admin.providers.update', $provider) }}" class="mt-6 space-y-6">
            @csrf
            @method('PUT')
            <input type="hidden" name="code" value="{{ $provider->code }}">

            <div class="form-grid-2">
                <div class="form-field">
                    <label for="name" class="form-label">Nama Provider</label>
                    <input id="name" name="name" class="form-input" value="{{ old('name', $provider->name) }}"
                        placeholder="Tripay">
                    @error('name')
                        <p class="field-error">{{ $message }}</p>
                    @enderror
                </div>

                <div class="form-field">
                    <label for="merchant_code" class="form-label">Merchant Code</label>
                    <input id="merchant_code" name="merchant_code" class="form-input"
                        value="{{ old('merchant_code', $providerConfig['merchant_code']) }}" placeholder="TRIPAY">
                    @error('merchant_code')
                        <p class="field-error">{{ $message }}</p>
                    @enderror
                </div>

                <div class="form-field">
                    <label for="api_base_url" class="form-label">API Base URL</label>
                    <input id="api_base_url" name="api_base_url" class="form-input"
                        value="{{ old('api_base_url', $providerConfig['api_base_url']) }}"
                        placeholder="https://tripay.co.id/api">
                    @error('api_base_url')
                        <p class="field-error">{{ $message }}</p>
                    @enderror
                </div>

                <div class="form-field">
                    <label for="public_base_url" class="form-label">Public Base URL</label>
                    <input id="public_base_url" name="public_base_url" class="form-input"
                        value="{{ old('public_base_url', $providerConfig['public_base_url']) }}"
                        placeholder="https://tripay.co.id">
                    @error('public_base_url')
                        <p class="field-error">{{ $message }}</p>
                    @enderror
                </div>

                <div class="form-field">
                    <label for="return_url" class="form-label">Return URL</label>
                    <input id="return_url" name="return_url" class="form-input"
                        value="{{ old('return_url', $providerConfig['return_url']) }}"
                        placeholder="https://merchant.test/payment/return">
                    @error('return_url')
                        <p class="field-error">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <div class="form-grid-2">
                <div class="form-field">
                    <label for="api_key" class="form-label">API Key</label>
                    <input id="api_key" name="api_key" type="password" class="form-input" value=""
                        placeholder="Isi untuk mengganti credential">
                    <p class="field-help">Kosongkan untuk mempertahankan API key yang sudah tersimpan.</p>
                    @error('api_key')
                        <p class="field-error">{{ $message }}</p>
                    @enderror
                </div>

                <div class="form-field">
                    <label for="private_key" class="form-label">Private Key</label>
                    <input id="private_key" name="private_key" type="password" class="form-input" value=""
                        placeholder="Isi untuk mengganti credential">
                    <p class="field-help">Digunakan untuk signature request atau callback verification.</p>
                    @error('private_key')
                        <p class="field-error">{{ $message }}</p>
                    @enderror
                </div>

                <div class="form-field">
                    <label for="client_key" class="form-label">Client Key</label>
                    <input id="client_key" name="client_key" type="password" class="form-input" value=""
                        placeholder="Midtrans atau provider serupa">
                    @error('client_key')
                        <p class="field-error">{{ $message }}</p>
                    @enderror
                </div>

                <div class="form-field">
                    <label for="server_key" class="form-label">Server Key</label>
                    <input id="server_key" name="server_key" type="password" class="form-input" value=""
                        placeholder="Midtrans atau provider serupa">
                    @error('server_key')
                        <p class="field-error">{{ $message }}</p>
                    @enderror
                </div>

                <div class="form-field">
                    <label for="secret_key" class="form-label">Secret Key</label>
                    <input id="secret_key" name="secret_key" type="password" class="form-input" value=""
                        placeholder="Xendit atau provider serupa">
                    @error('secret_key')
                        <p class="field-error">{{ $message }}</p>
                    @enderror
                </div>

                <div class="form-field">
                    <label for="callback_token" class="form-label">Callback Token</label>
                    <input id="callback_token" name="callback_token" type="password" class="form-input" value=""
                        placeholder="Token verifikasi callback">
                    @error('callback_token')
                        <p class="field-error">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <div class="form-grid-2">
                <label class="checkbox-field">
                    <input type="hidden" name="is_active" value="0">
                    <input class="checkbox-input" type="checkbox" name="is_active" value="1"
                        @checked(old('is_active', $provider->is_active))>
                    <span>
                        <span class="checkbox-title">Provider aktif</span>
                        <span class="checkbox-copy">Provider akan tersedia untuk pemilihan di client API.</span>
                    </span>
                </label>

                <label class="checkbox-field">
                    <input type="hidden" name="sandbox_mode" value="0">
                    <input class="checkbox-input" type="checkbox" name="sandbox_mode" value="1"
                        @checked(old('sandbox_mode', $provider->sandbox_mode))>
                    <span>
                        <span class="checkbox-title">Gunakan mode sandbox</span>
                        <span class="checkbox-copy">Adapter akan memakai endpoint sandbox jika provider mendukung.</span>
                    </span>
                </label>

                <label class="checkbox-field">
                    <input type="hidden" name="supports_refund_api" value="0">
                    <input class="checkbox-input" type="checkbox" name="supports_refund_api" value="1"
                        @checked(old('supports_refund_api', ! empty($providerConfig['supports_refund_api'])))>
                    <span>
                        <span class="checkbox-title">Refund via API didukung</span>
                        <span class="checkbox-copy">Aktifkan hanya jika provider memang menyediakan endpoint refund.</span>
                    </span>
                </label>
            </div>

            <div class="form-field">
                <label for="extra_config" class="form-label">Extra Config JSON</label>
                <textarea id="extra_config" name="extra_config" class="form-textarea"
                    placeholder='{"channel_category":"virtual_account","issuer":"BCA"}'>{{ old('extra_config', ! empty($extraConfig) ? json_encode($extraConfig, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : '') }}</textarea>
                <p class="field-help">Gunakan untuk key tambahan yang belum memiliki field khusus di form ini.</p>
                @error('extra_config')
                    <p class="field-error">{{ $message }}</p>
                @enderror
            </div>

            <div class="form-actions">
                <button type="submit" class="button-primary">Simpan Perubahan</button>
                <a href="{{ route('admin.providers') }}" class="button-link">Kembali ke daftar provider</a>
            </div>
        </form>
    </section>

    <section class="panel-card">
        <div class="panel-heading">
            <div>
                <p class="section-kicker">Aplikasi Terhubung</p>
                <h3 class="section-title">Daftar aplikasi yang memakai provider ini</h3>
            </div>
        </div>

        <div class="stack-list mt-6">
            @forelse ($provider->applications as $application)
                <div class="stack-item">
                    <p class="stack-title">{{ $application->name }}</p>
                    <p class="stack-meta">{{ $application->code }} &middot; {{ $application->status ? 'Aktif' : 'Tidak Aktif' }}</p>
                </div>
            @empty
                <div class="empty-state">Belum ada aplikasi yang terhubung langsung ke provider ini.</div>
            @endforelse
        </div>
    </section>

    <section class="split-grid">
        <article class="panel-card">
            <div class="panel-heading">
                <div>
                    <p class="section-kicker">Metode Pembayaran</p>
                    <h3 class="section-title">Daftar layanan yang tersedia</h3>
                </div>
            </div>

            <div class="stack-list">
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
    </section>
@endsection
