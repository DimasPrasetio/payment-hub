@extends('layouts.admin')

@section('title', $pageTitle)

@php
    $identityItems = [
        ['label' => 'Order Eksternal', 'value' => $paymentOrder->external_order_id ?? '-', 'code' => false],
        ['label' => 'Referensi Merchant', 'value' => $paymentOrder->merchant_ref, 'code' => true],
        ['label' => 'Nama Pelanggan', 'value' => $paymentOrder->customer_name ?? '-', 'code' => false],
        ['label' => 'Email', 'value' => $paymentOrder->customer_email ?? '-', 'code' => false],
        ['label' => 'Telepon', 'value' => $paymentOrder->customer_phone ?? '-', 'code' => false],
        ['label' => 'Dibuat Pada', 'value' => $paymentOrder->created_at?->format('d M Y H:i') ?? '-', 'code' => false],
        ['label' => 'Kedaluwarsa Pada', 'value' => $paymentOrder->expires_at?->format('d M Y H:i') ?? '-', 'code' => false],
        ['label' => 'Dibayar Pada', 'value' => $paymentOrder->paid_at?->format('d M Y H:i') ?? '-', 'code' => false],
    ];
    $routingItems = [
        ['label' => 'Aplikasi', 'value' => $paymentOrder->application ? $paymentOrder->application->name . ' (' . $paymentOrder->application->code . ')' : '-', 'code' => false],
        ['label' => 'Provider Default Aplikasi', 'value' => $paymentOrder->application?->default_provider ?? '-', 'code' => false],
        ['label' => 'Saluran Pembayaran', 'value' => $paymentOrder->provider_code ?? '-', 'code' => false],
        ['label' => 'Metode Pembayaran', 'value' => $paymentOrder->payment_method ?? '-', 'code' => false],
        ['label' => 'ID Tenant', 'value' => $paymentOrder->tenant_id ?? '-', 'code' => false],
        ['label' => 'Kunci Idempotensi', 'value' => $paymentOrder->idempotency_key ?? '-', 'code' => true],
        ['label' => 'Metadata', 'value' => $paymentOrder->metadata ? json_encode($paymentOrder->metadata, JSON_UNESCAPED_SLASHES) : '-', 'code' => true],
    ];
@endphp

@section('content')
    <x-page-hero :kicker="$pageKicker" :title="$pageHeading" :description="$pageDescription">
        <x-slot:actions>
            <a href="{{ route('admin.transactions') }}" class="provider-hero-link">Kembali ke Riwayat</a>
            @if ($paymentOrder->application)
                <a href="{{ route('admin.applications.show', $paymentOrder->application) }}" class="provider-hero-link">Buka Aplikasi</a>
            @endif
        </x-slot:actions>
    </x-page-hero>

    <section class="metric-grid">
        <x-metric-card label="Jumlah" :value="'IDR ' . number_format($paymentOrder->amount, 0, ',', '.')"
            caption="Nominal transaksi" tone="cyan" />
        <x-metric-card label="Status" :value="$paymentOrder->status?->value ?? (string) $paymentOrder->status"
            caption="Status transaksi saat ini" :tone="$paymentOrder->status?->tone() ?? 'slate'" />
        <x-metric-card label="Aplikasi" :value="$paymentOrder->application?->code ?? '-'"
            :caption="$paymentOrder->application?->name ?? 'Tidak ada aplikasi'" tone="emerald" />
        <x-metric-card label="Saluran Pembayaran" :value="$paymentOrder->provider_code"
            :caption="$paymentOrder->payment_method" tone="violet" />
    </section>

    <section class="workspace-grid">
        <div class="workspace-main">
            <article class="panel-card">
                <div class="panel-heading">
                    <div>
                        <p class="section-kicker">Referensi Provider</p>
                        <h3 class="section-title">Catatan transaksi di saluran pembayaran</h3>
                    </div>
                </div>

                <div class="stack-list mt-6">
                    @forelse ($paymentOrder->providerTransactions as $transaction)
                        <div class="stack-item">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <p class="stack-title">{{ $transaction->provider_reference ?? 'Referensi provider belum tersedia' }}</p>
                                    <p class="stack-meta">{{ $transaction->provider }} &middot; {{ $transaction->payment_method ?? '-' }}</p>
                                </div>
                                <p class="stack-value">{{ $transaction->pay_code ?? 'Belum ada pay code' }}</p>
                            </div>
                            <p class="table-copy mt-3">
                                Merchant ref {{ $transaction->merchant_ref }}
                                @if ($transaction->payment_url)
                                    &middot; <a href="{{ $transaction->payment_url }}" target="_blank" rel="noreferrer" class="text-cyan-600">Buka payment URL</a>
                                @endif
                            </p>
                        </div>
                    @empty
                        <div class="empty-state">Belum ada catatan saluran untuk pesanan ini.</div>
                    @endforelse
                </div>
            </article>

            <article class="panel-card">
                <div class="panel-heading">
                    <div>
                        <p class="section-kicker">Sinkronisasi Data</p>
                        <h3 class="section-title">Daftar percobaan pengiriman</h3>
                    </div>
                </div>

                <div class="stack-list mt-6">
                    @forelse ($paymentOrder->webhookDeliveries as $delivery)
                        <x-webhook-item :delivery="$delivery" />
                    @empty
                        <div class="empty-state">Belum ada notifikasi sinkronisasi untuk pesanan ini.</div>
                    @endforelse
                </div>
            </article>

            <article class="panel-card">
                <div class="panel-heading">
                    <div>
                        <p class="section-kicker">Catatan Aktivitas</p>
                        <h3 class="section-title">Aktivitas sistem untuk transaksi ini</h3>
                    </div>
                </div>

                <div class="stack-list mt-6">
                    @forelse ($paymentOrder->paymentEvents as $event)
                        <x-event-item :event="$event" />
                    @empty
                        <div class="empty-state">Belum ada catatan aktivitas untuk transaksi ini.</div>
                    @endforelse
                </div>
            </article>
        </div>

        <aside class="workspace-side">
            <article class="panel-card">
                <div class="panel-heading">
                    <div>
                        <p class="section-kicker">Ringkasan Transaksi</p>
                        <h3 class="section-title">Informasi utama pesanan</h3>
                    </div>
                    <x-status-badge :label="$paymentOrder->status?->value ?? (string) $paymentOrder->status" :tone="$paymentOrder->status?->tone() ?? 'slate'" />
                </div>

                <div class="admin-summary-list mt-6">
                    @foreach ($identityItems as $item)
                        <div class="admin-summary-item">
                            <p class="admin-summary-label">{{ $item['label'] }}</p>
                            <p class="{{ $item['code'] ? 'admin-summary-code' : 'admin-summary-value' }}">{{ $item['value'] }}</p>
                        </div>
                    @endforeach
                </div>
            </article>

            <details class="disclosure-card">
                <summary class="disclosure-summary">
                    <div>
                        <p class="section-kicker">Routing dan Kepemilikan</p>
                        <p class="disclosure-title">Informasi teknis transaksi</p>
                        <p class="disclosure-meta">Provider, idempotency key, tenant, dan metadata.</p>
                    </div>
                    <span class="disclosure-indicator">Lihat</span>
                </summary>
                <div class="disclosure-body">
                    <div class="admin-summary-list">
                        @foreach ($routingItems as $item)
                            <div class="admin-summary-item">
                                <p class="admin-summary-label">{{ $item['label'] }}</p>
                                <p class="{{ $item['code'] ? 'admin-summary-code' : 'admin-summary-value' }}">{{ $item['value'] }}</p>
                            </div>
                        @endforeach
                    </div>
                </div>
            </details>
        </aside>
    </section>
@endsection
