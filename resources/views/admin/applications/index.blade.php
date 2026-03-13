@extends('layouts.admin')

@section('title', $pageTitle)

@section('content')
    <x-page-hero :kicker="$pageKicker" :title="$pageHeading" :description="$pageDescription" compact>
        <x-slot:actions>
            <a href="{{ route('admin.applications.create') }}" class="provider-hero-link">Tambah Aplikasi</a>
        </x-slot:actions>

        <div class="page-hero-stat">
            <span class="page-hero-label">Gunakan Halaman Ini</span>
            <span class="page-hero-value">Cari aplikasi, cek provider default, dan buka detail konfigurasi.</span>
        </div>
        <div class="page-hero-stat">
            <span class="page-hero-label">Panduan Cepat</span>
            <span class="page-hero-value">Filter hanya jika data sudah banyak. Jika tidak, biarkan semua kosong.</span>
        </div>
    </x-page-hero>

    <section class="panel-card filter-panel">
        <div class="panel-heading">
            <div>
                <p class="section-kicker">Filter Daftar</p>
                <h3 class="section-title">Saring aplikasi yang ingin ditinjau</h3>
                <p class="section-copy">Isi hanya kolom yang diperlukan. Sisanya bisa dibiarkan kosong.</p>
            </div>
        </div>

        <form method="GET" class="filter-grid">
            <div class="form-field form-field-wide">
                <label for="q" class="form-label">Cari Aplikasi</label>
                <input id="q" name="q" value="{{ $filters['q'] ?? '' }}" class="form-input"
                    placeholder="Kode, nama, atau URL notifikasi">
            </div>
            <div class="form-field">
                <label for="provider" class="form-label">Saluran Utama</label>
                <select id="provider" name="provider" class="form-select">
                    <option value="">Semua Saluran</option>
                    @foreach ($providers as $code => $name)
                        <option value="{{ $code }}" @selected(($filters['provider'] ?? null) === $code)>{{ $name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-field">
                <label for="status" class="form-label">Status Aplikasi</label>
                <select id="status" name="status" class="form-select">
                    <option value="">Semua Status</option>
                    <option value="active" @selected(($filters['status'] ?? null) === 'active')>Aktif</option>
                    <option value="inactive" @selected(($filters['status'] ?? null) === 'inactive')>Tidak Aktif</option>
                </select>
            </div>
            <div class="form-actions">
                <button type="submit" class="button-primary">Terapkan Filter</button>
                <a href="{{ route('admin.applications') }}" class="button-link">Reset Filter</a>
            </div>
        </form>
    </section>

    <section class="card-grid">
        @forelse ($applicationsList as $application)
            <article class="panel-card">
                <div class="panel-heading">
                    <div>
                        <p class="section-kicker">{{ $application->code }}</p>
                        <h3 class="section-title">{{ $application->name }}</h3>
                    </div>
                    <x-status-badge :label="$application->status ? 'Aktif' : 'Tidak Aktif'" :tone="$application->status ? 'emerald' : 'slate'" />
                </div>

                <dl class="description-list mt-6">
                    <div class="description-item">
                        <dt>Saluran Utama</dt>
                        <dd>{{ $application->defaultProvider?->name ?? $application->default_provider }}</dd>
                    </div>
                    <div class="description-item">
                        <dt>URL Notifikasi</dt>
                        <dd>{{ \Illuminate\Support\Str::limit($application->webhook_url, 40) }}</dd>
                    </div>
                    <div class="description-item">
                        <dt>Total Transaksi</dt>
                        <dd>{{ number_format($application->payment_orders_count) }}</dd>
                    </div>
                    <div class="description-item">
                        <dt>Tingkat Keberhasilan</dt>
                        <dd>{{ $application->payment_orders_count > 0 ? number_format(($application->paid_orders_count / $application->payment_orders_count) * 100, 1) . '%' : '0.0%' }}
                        </dd>
                    </div>
                    <div class="description-item">
                        <dt>Notifikasi Gagal</dt>
                        <dd>{{ number_format($application->failed_webhook_deliveries_count) }}</dd>
                    </div>
                    <div class="description-item">
                        <dt>Total Nominal</dt>
                        <dd>IDR {{ number_format($application->gross_amount ?? 0, 0, ',', '.') }}</dd>
                    </div>
                </dl>

                <a href="{{ route('admin.applications.show', $application) }}" class="button-link mt-6">Lihat Detail</a>
            </article>
        @empty
            <div class="empty-state">Belum ada aplikasi yang terdaftar.</div>
        @endforelse
    </section>

    <div class="pagination-wrap">
        {{ $applicationsList->links() }}
    </div>
@endsection
