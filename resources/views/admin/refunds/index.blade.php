@extends('layouts.admin')

@section('title', $pageTitle)

@section('content')
    <section class="panel-card">
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
