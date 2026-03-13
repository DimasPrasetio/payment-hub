@extends('layouts.admin')

@section('title', $pageTitle)

@section('content')
    <x-page-hero :kicker="$pageKicker" :title="$pageHeading" :description="$pageDescription" compact>
        <div class="page-hero-stat">
            <span class="page-hero-label">Tujuan Halaman</span>
            <span class="page-hero-value">Pantau notifikasi ke aplikasi client dan cek mana yang sukses, menunggu, atau gagal.</span>
        </div>
        <div class="page-hero-stat">
            <span class="page-hero-label">Arah Investigasi</span>
            <span class="page-hero-value">Mulai dari status pengiriman, lalu sempitkan dengan aplikasi, provider, atau payment ID.</span>
        </div>
    </x-page-hero>

    <section class="panel-card filter-panel">
        <div class="panel-heading">
            <div>
                <p class="section-kicker">Filter Pengiriman</p>
                <h3 class="section-title">Pilih notifikasi yang ingin diperiksa</h3>
                <p class="section-copy">Gunakan kombinasi paling sederhana agar hasil tetap mudah dipahami.</p>
            </div>
        </div>

        <form method="GET" class="filter-grid">
            <div class="form-field">
                <label for="application" class="form-label">Aplikasi Asal</label>
                <select id="application" name="application" class="form-select">
                    <option value="">Semua Aplikasi</option>
                    @foreach ($applications as $code => $name)
                        <option value="{{ $code }}" @selected(($filters['application'] ?? null) === $code)>{{ $name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-field">
                <label for="provider" class="form-label">Saluran Pembayaran</label>
                <select id="provider" name="provider" class="form-select">
                    <option value="">Semua Saluran</option>
                    @foreach ($providers as $code => $name)
                        <option value="{{ $code }}" @selected(($filters['provider'] ?? null) === $code)>{{ $name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-field">
                <label for="status" class="form-label">Status Pengiriman</label>
                <select id="status" name="status" class="form-select">
                    <option value="">Semua Status</option>
                    @foreach (['pending', 'success', 'failed'] as $status)
                        <option value="{{ $status }}" @selected(($filters['status'] ?? null) === $status)>{{ $status }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-field">
                <label for="event_type" class="form-label">Jenis Event</label>
                <input id="event_type" name="event_type" value="{{ $filters['event_type'] ?? '' }}" class="form-input"
                    placeholder="payment.paid">
            </div>
            <div class="form-field">
                <label for="payment" class="form-label">ID Pembayaran</label>
                <input id="payment" name="payment" value="{{ $filters['payment'] ?? '' }}" class="form-input"
                    placeholder="pay_...">
            </div>
            <div class="form-actions">
                <button type="submit" class="button-primary">Terapkan Filter</button>
                <a href="{{ route('admin.webhooks') }}" class="button-link">Reset Filter</a>
            </div>
        </form>
    </section>

    <section class="metric-grid">
        <x-metric-card label="Total Notifikasi" :value="number_format($summary['total_deliveries'])"
            caption="Jumlah notifikasi yang dikirim" tone="cyan" />
        <x-metric-card label="Sukses" :value="number_format($summary['successful_deliveries'])"
            caption="Notifikasi berhasil diterima" tone="emerald" />
        <x-metric-card label="Menunggu" :value="number_format($summary['pending_deliveries'])"
            caption="Notifikasi sedang diproses atau diulang" tone="amber" />
        <x-metric-card label="Gagal" :value="number_format($summary['failed_deliveries'])"
            caption="Notifikasi yang butuh perhatian" tone="rose" />
    </section>

    <section class="panel-card">
        <div class="stack-list">
            @forelse ($deliveries as $delivery)
                <x-webhook-item :delivery="$delivery" />
            @empty
                <div class="empty-state">Belum ada riwayat notifikasi sinkronisasi.</div>
            @endforelse
        </div>

        <div class="pagination-wrap">
            {{ $deliveries->links() }}
        </div>
    </section>
@endsection
