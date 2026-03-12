@extends('layouts.admin')

@section('title', $pageTitle)

@section('content')
    <section class="page-section">
        <div class="metric-grid">
            @foreach ($summaryCards as $card)
                @php
                    $displayValue = in_array($card['label'], ['Paid conversion', 'Webhook success'], true)
                        ? number_format($card['value'], 1) . '%'
                        : number_format($card['value']);
                    $displayCaption = match ($card['label']) {
                        'Total transactions' => 'IDR ' . number_format($card['amount'], 0, ',', '.') . ' gross volume',
                        'Paid conversion' => number_format($card['amount']) . ' successful payments',
                        'Webhook success' => number_format($card['amount']) . ' pending retries',
                        default => $card['caption'],
                    };
                @endphp

                <x-metric-card :label="$card['label']" :value="$displayValue" :caption="$displayCaption"
                    :tone="$card['tone']" />
            @endforeach
        </div>
    </section>

    <section class="page-section">
        <div class="panel-heading">
            <div>
                <p class="section-kicker">Ringkasan</p>
                <h3 class="section-title">Status Transaksi Saat Ini</h3>
            </div>
        </div>

        <div class="status-grid">
            @forelse ($statusCards as $status)
                <x-status-card :status="$status" />
            @empty
                <div class="empty-state">Belum ada transaksi yang bisa dihitung.</div>
            @endforelse
        </div>
    </section>

    <section class="split-grid">
        <article class="panel-card">
            <div class="panel-heading">
                <div>
                    <p class="section-kicker">Transaksi</p>
                    <h3 class="section-title">Transaksi Pembayaran Terbaru</h3>
                </div>
                <a href="{{ route('admin.transactions') }}" class="button-link">Lihat semua</a>
            </div>

            <div class="data-table mt-6">
                <div class="table-head">
                    <span>Pembayaran</span>
                    <span>Pelanggan</span>
                    <span>Saluran</span>
                    <span>Jumlah</span>
                    <span>Status</span>
                </div>
                @forelse ($latestTransactions as $payment)
                    <x-payment-row :payment="$payment" />
                @empty
                    <div class="empty-state">Belum ada transaksi pembayaran.</div>
                @endforelse
            </div>
        </article>

        <article class="panel-card">
            <div class="panel-heading">
                <div>
                    <p class="section-kicker">Sistem</p>
                    <h3 class="section-title">Riwayat Sinkronisasi Data</h3>
                </div>
                <a href="{{ route('admin.webhooks') }}" class="button-link">Lihat antrean</a>
            </div>

            <div class="stack-list">
                @forelse ($latestWebhookDeliveries as $delivery)
                    <x-webhook-item :delivery="$delivery" />
                @empty
                    <div class="empty-state">Belum ada data sinkronisasi webhook.</div>
                @endforelse
            </div>
        </article>
    </section>

    <section class="split-grid">
        <article class="panel-card">
            <div class="panel-heading">
                <div>
                    <p class="section-kicker">Saluran Pembayaran</p>
                    <h3 class="section-title">Kondisi Saluran Saat Ini</h3>
                </div>
                <a href="{{ route('admin.providers') }}" class="button-link">Kelola saluran</a>
            </div>

            <div class="provider-grid">
                @forelse ($providerCards as $provider)
                    <x-provider-card :provider="$provider" />
                @empty
                    <div class="empty-state">Belum ada provider.</div>
                @endforelse
            </div>
        </article>

        <article class="panel-card">
            <div class="panel-heading">
                <div>
                    <p class="section-kicker">Aplikasi Terhubung</p>
                    <h3 class="section-title">Aktivitas Aplikasi</h3>
                </div>
                <a href="{{ route('admin.applications') }}" class="button-link">Buka daftar aplikasi</a>
            </div>

            <div class="stack-list">
                @forelse ($applicationCards as $application)
                    <x-app-item :application="$application" />
                @empty
                    <div class="empty-state">Belum ada aplikasi yang terdaftar.</div>
                @endforelse
            </div>
        </article>
    </section>

    <section class="split-grid">
        <article class="panel-card">
            <div class="panel-heading">
                <div>
                    <p class="section-kicker">Catatan Sistem</p>
                    <h3 class="section-title">Log Aktivitas Terbaru</h3>
                </div>
                <a href="{{ route('admin.audit-trail') }}" class="button-link">Lihat linimasa</a>
            </div>

            <div class="stack-list">
                @forelse ($latestEvents as $event)
                    <x-event-item :event="$event" />
                @empty
                    <div class="empty-state">Belum ada event audit trail.</div>
                @endforelse
            </div>
        </article>

        <article class="panel-card">
            <div class="panel-heading">
                <div>
                    <p class="section-kicker">Pengecekan Data</p>
                    <h3 class="section-title">Peringatan Sistem</h3>
                </div>
                <a href="{{ route('admin.reconciliation') }}" class="button-link">Buka rekonsiliasi</a>
            </div>

            <div class="stack-list">
                <div class="stack-item">
                    <p class="stack-title">Transaksi hilang (provider)</p>
                    <p class="stack-value">{{ number_format($reconciliationSummary['missing_provider_transaction']) }}</p>
                </div>
                <div class="stack-item">
                    <p class="stack-title">Gagal sinkronisasi data (webhook)</p>
                    <p class="stack-value">{{ number_format($reconciliationSummary['missing_successful_webhook']) }}</p>
                </div>
                <div class="stack-item">
                    <p class="stack-title">Berhasil namun notifikasi tertunda</p>
                    <p class="stack-value">{{ number_format($reconciliationSummary['paid_without_paid_event']) }}</p>
                </div>
                <div class="stack-item">
                    <p class="stack-title">Kedaluwarsa tetapi belum dibatalkan</p>
                    <p class="stack-value">{{ number_format($reconciliationSummary['expired_but_still_pending']) }}</p>
                </div>
            </div>
        </article>
    </section>
@endsection