@extends('layouts.admin')

@section('title', $pageTitle)

@section('content')
    <section class="panel-card">
        <form method="GET" class="filter-grid">
            <div class="form-field form-field-wide">
                <label for="q" class="form-label">Cari Transaksi</label>
                <input id="q" name="q" value="{{ $filters['q'] ?? '' }}" class="form-input"
                    placeholder="ID pembayaran, referensi, pelanggan, atau order ID">
            </div>
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
                <label for="status" class="form-label">Status Transaksi</label>
                <select id="status" name="status" class="form-select">
                    <option value="">Semua Status</option>
                    @foreach (['CREATED', 'PENDING', 'PAID', 'FAILED', 'EXPIRED', 'REFUNDED'] as $status)
                        <option value="{{ $status }}" @selected(($filters['status'] ?? null) === $status)>{{ $status }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-field">
                <label for="payment_method" class="form-label">Metode Pembayaran</label>
                <select id="payment_method" name="payment_method" class="form-select">
                    <option value="">Semua Metode</option>
                    @foreach ($paymentMethods as $method)
                        <option value="{{ $method }}" @selected(($filters['payment_method'] ?? null) === $method)>{{ $method }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="form-field">
                <label for="date_from" class="form-label">Dari Tanggal</label>
                <input id="date_from" type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}"
                    class="form-input">
            </div>
            <div class="form-field">
                <label for="date_to" class="form-label">Sampai Tanggal</label>
                <input id="date_to" type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}" class="form-input">
            </div>
            <div class="form-actions">
                <button type="submit" class="button-primary">Terapkan Filter</button>
                <a href="{{ route('admin.transactions') }}" class="button-link">Reset Filter</a>
            </div>
        </form>
    </section>

    <section class="metric-grid">
        <x-metric-card label="Transaksi Tersaring" :value="number_format($summary['total_orders'])"
            caption="Jumlah transaksi yang sesuai filter" tone="cyan" />
        <x-metric-card label="Total Nominal Tersaring" :value="'IDR ' . number_format($summary['gross_amount'], 0, ',', '.')" caption="Total nilai transaksi berdasarkan filter" tone="emerald" />
    </section>

    <section class="panel-card">
        <div class="panel-heading">
            <div>
                <p class="section-kicker">Daftar Transaksi</p>
                <h3 class="section-title">Riwayat Pesanan Pembayaran</h3>
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
            @forelse ($orders as $payment)
                <x-payment-row :payment="$payment" />
            @empty
                <div class="empty-state">Tidak ada transaksi yang cocok dengan filter saat ini.</div>
            @endforelse
        </div>

        <div class="pagination-wrap">
            {{ $orders->links() }}
        </div>
    </section>
@endsection