@extends('layouts.admin')

@section('title', $pageTitle)

@section('content')
    <section class="panel-card">
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