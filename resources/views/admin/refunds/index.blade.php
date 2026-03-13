@extends('layouts.admin')

@section('title', $pageTitle)

@section('content')
    <x-page-hero :kicker="$pageKicker" :title="$pageHeading" :description="$pageDescription" compact>
        <div class="page-hero-stat">
            <span class="page-hero-label">Fungsi Halaman</span>
            <span class="page-hero-value">Melihat order yang sudah dibayar atau sudah direfund sebagai dasar proses pengembalian dana.</span>
        </div>
        <div class="page-hero-stat">
            <span class="page-hero-label">Catatan</span>
            <span class="page-hero-value">Gunakan filter provider dan status agar daftar kandidat refund lebih mudah dibaca.</span>
        </div>
    </x-page-hero>

    <section class="panel-card filter-panel">
        <div class="panel-heading">
            <div>
                <p class="section-kicker">Filter Refund</p>
                <h3 class="section-title">Pilih order yang ingin ditinjau</h3>
                <p class="section-copy">Halaman ini bersifat operasional. Fokuskan pencarian pada provider dan status refund.</p>
            </div>
        </div>

        <form method="GET" class="filter-grid">
            <div class="form-field">
                <label for="application" class="form-label">Application</label>
                <select id="application" name="application" class="form-select">
                    <option value="">All applications</option>
                    @foreach ($applications as $code => $name)
                        <option value="{{ $code }}" @selected(($filters['application'] ?? null) === $code)>{{ $name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-field">
                <label for="provider" class="form-label">Provider</label>
                <select id="provider" name="provider" class="form-select">
                    <option value="">All providers</option>
                    @foreach ($providers as $code => $name)
                        <option value="{{ $code }}" @selected(($filters['provider'] ?? null) === $code)>{{ $name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-field">
                <label for="status" class="form-label">Status</label>
                <select id="status" name="status" class="form-select">
                    <option value="">Paid and refunded</option>
                    <option value="paid" @selected(($filters['status'] ?? null) === 'paid')>Paid</option>
                    <option value="refunded" @selected(($filters['status'] ?? null) === 'refunded')>Refunded</option>
                </select>
            </div>
            <div class="form-actions">
                <button type="submit" class="button-primary">Apply filters</button>
                <a href="{{ route('admin.refunds') }}" class="button-link">Reset</a>
            </div>
        </form>
    </section>

    <section class="metric-grid">
        <x-metric-card label="Paid orders" :value="number_format($summary['paid_orders'])" caption="Orders that are candidates for refund flow" tone="emerald" />
        <x-metric-card label="Refunded orders" :value="number_format($summary['refunded_orders'])" caption="Orders already marked refunded" tone="violet" />
    </section>

    <section class="panel-card">
        <div class="data-table">
            <div class="table-head">
                <span>Payment</span>
                <span>Customer</span>
                <span>Provider</span>
                <span>Amount</span>
                <span>Status</span>
            </div>
            @forelse ($orders as $payment)
                <x-payment-row :payment="$payment" />
            @empty
                <div class="empty-state">Belum ada order paid/refunded.</div>
            @endforelse
        </div>

        <div class="pagination-wrap">
            {{ $orders->links() }}
        </div>
    </section>
@endsection
