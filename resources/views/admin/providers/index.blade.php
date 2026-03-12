@extends('layouts.admin')

@section('title', $pageTitle)

@section('content')
    <section class="panel-card">
        <form method="GET" class="filter-grid">
            <div class="form-field form-field-wide">
                <label for="q" class="form-label">Cari Saluran</label>
                <input id="q" name="q" value="{{ $filters['q'] ?? '' }}" class="form-input"
                    placeholder="Kode atau nama saluran">
            </div>
            <div class="form-field">
                <label for="status" class="form-label">Status</label>
                <select id="status" name="status" class="form-select">
                    <option value="">Semua Status</option>
                    <option value="active" @selected(($filters['status'] ?? null) === 'active')>Aktif</option>
                    <option value="inactive" @selected(($filters['status'] ?? null) === 'inactive')>Tidak Aktif</option>
                </select>
            </div>
            <div class="form-field">
                <label for="mode" class="form-label">Lingkungan</label>
                <select id="mode" name="mode" class="form-select">
                    <option value="">Semua Lingkungan</option>
                    <option value="production" @selected(($filters['mode'] ?? null) === 'production')>Produksi</option>
                    <option value="sandbox" @selected(($filters['mode'] ?? null) === 'sandbox')>Uji Coba</option>
                </select>
            </div>
            <div class="form-actions">
                <button type="submit" class="button-primary">Terapkan Filter</button>
                <a href="{{ route('admin.providers') }}" class="button-link">Reset Filter</a>
            </div>
        </form>
    </section>

    <section class="card-grid">
        @forelse ($providersList as $provider)
            <article class="panel-card">
                <div class="panel-heading">
                    <div>
                        <p class="section-kicker">{{ $provider->code }}</p>
                        <h3 class="section-title">{{ $provider->name }}</h3>
                    </div>
                    <x-status-badge :label="$provider->is_active ? 'Aktif' : 'Tidak Aktif'" :tone="$provider->is_active ? 'emerald' : 'slate'" />
                </div>

                <dl class="description-list mt-6">
                    <div class="description-item">
                        <dt>Lingkungan</dt>
                        <dd>{{ $provider->sandbox_mode ? 'Uji Coba (Sandbox)' : 'Produksi' }}</dd>
                    </div>
                    <div class="description-item">
                        <dt>Metode Terhubung</dt>
                        <dd>{{ $provider->active_payment_method_mappings_count }}/{{ $provider->payment_method_mappings_count }}
                        </dd>
                    </div>
                    <div class="description-item">
                        <dt>Total Transaksi</dt>
                        <dd>{{ number_format($provider->payment_orders_count) }}</dd>
                    </div>
                    <div class="description-item">
                        <dt>Tingkat Keberhasilan</dt>
                        <dd>{{ $provider->payment_orders_count > 0 ? number_format(($provider->paid_payment_orders_count / $provider->payment_orders_count) * 100, 1) . '%' : '0.0%' }}
                        </dd>
                    </div>
                    <div class="description-item">
                        <dt>Total Nominal</dt>
                        <dd>IDR {{ number_format($provider->gross_amount ?? 0, 0, ',', '.') }}</dd>
                    </div>
                </dl>

                <a href="{{ route('admin.providers.show', $provider) }}" class="button-link mt-6">Lihat Detail</a>
            </article>
        @empty
            <div class="empty-state">Belum ada saluran pembayaran.</div>
        @endforelse
    </section>

    <div class="pagination-wrap">
        {{ $providersList->links() }}
    </div>
@endsection