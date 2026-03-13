@extends('layouts.admin')

@section('title', $pageTitle)

@section('content')
    <x-page-hero :kicker="$pageKicker" :title="$pageHeading" :description="$pageDescription" compact>
        <div class="page-hero-stat">
            <span class="page-hero-label">Tujuan Halaman</span>
            <span class="page-hero-value">Mendeteksi transaksi yang butuh perhatian karena data provider, webhook, atau status tidak sinkron.</span>
        </div>
        <div class="page-hero-stat">
            <span class="page-hero-label">Cara Pakai</span>
            <span class="page-hero-value">Pilih jenis isu jika ingin fokus ke satu masalah. Kosongkan jika ingin melihat semuanya.</span>
        </div>
    </x-page-hero>

    <section class="panel-card filter-panel">
        <div class="panel-heading">
            <div>
                <p class="section-kicker">Filter Rekonsiliasi</p>
                <h3 class="section-title">Pilih anomali yang ingin ditinjau</h3>
                <p class="section-copy">Halaman ini dirancang untuk membantu operator menemukan selisih data secepat mungkin.</p>
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
                <label for="issue" class="form-label">Issue type</label>
                <select id="issue" name="issue" class="form-select">
                    <option value="">All issues</option>
                    <option value="missing_provider_transaction" @selected(($filters['issue'] ?? null) === 'missing_provider_transaction')>Missing provider transaction</option>
                    <option value="missing_successful_webhook" @selected(($filters['issue'] ?? null) === 'missing_successful_webhook')>Missing successful webhook</option>
                    <option value="paid_without_paid_event" @selected(($filters['issue'] ?? null) === 'paid_without_paid_event')>Paid without payment.paid event</option>
                    <option value="expired_but_still_pending" @selected(($filters['issue'] ?? null) === 'expired_but_still_pending')>Expired but still pending</option>
                </select>
            </div>
            <div class="form-actions">
                <button type="submit" class="button-primary">Apply filters</button>
                <a href="{{ route('admin.reconciliation') }}" class="button-link">Reset</a>
            </div>
        </form>
    </section>

    <section class="panel-card">
        <div class="data-table">
            <div class="table-head table-head-reconciliation">
                <span>Payment</span>
                <span>Application</span>
                <span>Provider</span>
                <span>Issues</span>
                <span>Status</span>
            </div>
            @forelse ($orders as $payment)
                <div class="table-row table-row-reconciliation">
                    <div>
                        <a href="{{ route('admin.transactions.show', $payment) }}" class="table-primary-link">{{ $payment->public_id }}</a>
                        <p class="table-meta">{{ $payment->external_order_id }}</p>
                    </div>
                    <div>
                        <p class="table-primary">{{ $payment->application?->code }}</p>
                        <p class="table-meta">{{ $payment->application?->name }}</p>
                    </div>
                    <div>
                        <p class="table-primary">{{ $payment->provider_code }}</p>
                        <p class="table-meta">{{ $payment->payment_method }}</p>
                    </div>
                    <div>
                        <div class="flex flex-wrap gap-2">
                            @foreach ($payment->reconciliation_issues as $issue)
                                <x-status-badge :label="$issue" tone="rose" />
                            @endforeach
                        </div>
                    </div>
                    <div>
                        <x-status-badge :label="$payment->status?->value ?? (string) $payment->status" :tone="$payment->status?->tone() ?? 'slate'" />
                    </div>
                </div>
            @empty
                <div class="empty-state">Tidak ada transaksi yang terdeteksi bermasalah untuk filter saat ini.</div>
            @endforelse
        </div>

        <div class="pagination-wrap">
            {{ $orders->links() }}
        </div>
    </section>
@endsection
