@extends('layouts.admin')

@section('title', $pageTitle)

@section('content')
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

    <section class="detail-grid">
        <article class="panel-card">
            <div class="panel-heading">
                <div>
                    <p class="section-kicker">Pelanggan</p>
                    <h3 class="section-title">Identitas Pesanan</h3>
                </div>
            </div>

            <dl class="description-list">
                <div class="description-item">
                    <dt>ID Publik</dt>
                    <dd>{{ $paymentOrder->public_id }}</dd>
                </div>
                <div class="description-item">
                    <dt>Order Eksternal</dt>
                    <dd>{{ $paymentOrder->external_order_id }}</dd>
                </div>
                <div class="description-item">
                    <dt>Referensi Merchant</dt>
                    <dd>{{ $paymentOrder->merchant_ref }}</dd>
                </div>
                <div class="description-item">
                    <dt>Nama Pelanggan</dt>
                    <dd>{{ $paymentOrder->customer_name }}</dd>
                </div>
                <div class="description-item">
                    <dt>Email</dt>
                    <dd>{{ $paymentOrder->customer_email }}</dd>
                </div>
                <div class="description-item">
                    <dt>Telepon</dt>
                    <dd>{{ $paymentOrder->customer_phone ?? '-' }}</dd>
                </div>
                <div class="description-item">
                    <dt>Dibuat Pada</dt>
                    <dd>{{ $paymentOrder->created_at?->format('d M Y H:i') }}</dd>
                </div>
                <div class="description-item">
                    <dt>Kedaluwarsa Pada</dt>
                    <dd>{{ $paymentOrder->expires_at?->format('d M Y H:i') ?? '-' }}</dd>
                </div>
                <div class="description-item">
                    <dt>Dibayar Pada</dt>
                    <dd>{{ $paymentOrder->paid_at?->format('d M Y H:i') ?? '-' }}</dd>
                </div>
            </dl>
        </article>

        <article class="panel-card">
            <div class="panel-heading">
                <div>
                    <p class="section-kicker">Informasi Tambahan</p>
                    <h3 class="section-title">Penentuan Jalur & Kepemilikan</h3>
                </div>
            </div>

            <dl class="description-list">
                <div class="description-item">
                    <dt>Aplikasi</dt>
                    <dd>{{ $paymentOrder->application?->name }} ({{ $paymentOrder->application?->code }})</dd>
                </div>
                <div class="description-item">
                    <dt>Saluran Utama</dt>
                    <dd>{{ $paymentOrder->application?->default_provider ?? '-' }}</dd>
                </div>
                <div class="description-item">
                    <dt>Saluran Pembayaran</dt>
                    <dd>{{ $paymentOrder->provider_code }}</dd>
                </div>
                <div class="description-item">
                    <dt>Metode Pembayaran</dt>
                    <dd>{{ $paymentOrder->payment_method }}</dd>
                </div>
                <div class="description-item">
                    <dt>ID Tenant</dt>
                    <dd>{{ $paymentOrder->tenant_id ?? '-' }}</dd>
                </div>
                <div class="description-item">
                    <dt>Kunci Idempotensi</dt>
                    <dd>{{ $paymentOrder->idempotency_key ?? '-' }}</dd>
                </div>
                <div class="description-item">
                    <dt>Data Metadata</dt>
                    <dd>{{ $paymentOrder->metadata ? json_encode($paymentOrder->metadata, JSON_UNESCAPED_SLASHES) : '-' }}
                    </dd>
                </div>
            </dl>
        </article>
    </section>

    <section class="split-grid">
        <article class="panel-card">
            <div class="panel-heading">
                <div>
                    <p class="section-kicker">Catatan Saluran Pembayaran</p>
                    <h3 class="section-title">Riwayat request ke sistem fihak ketiga</h3>
                </div>
            </div>

            <div class="stack-list">
                @forelse ($paymentOrder->providerTransactions as $transaction)
                    <div class="stack-item">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="stack-title">{{ $transaction->provider_reference ?? 'No provider reference yet' }}</p>
                                <p class="stack-meta">{{ $transaction->provider }} &middot;
                                    {{ $transaction->payment_method ?? '-' }}</p>
                            </div>
                            <p class="stack-value">{{ $transaction->pay_code ?? 'No pay code' }}</p>
                        </div>
                        <p class="table-copy mt-3">
                            Merchant ref {{ $transaction->merchant_ref }}
                            @if ($transaction->payment_url)
                                · <a href="{{ $transaction->payment_url }}" target="_blank" rel="noreferrer"
                                    class="text-cyan-600">payment URL</a>
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

            <div class="stack-list">
                @forelse ($paymentOrder->webhookDeliveries as $delivery)
                    <x-webhook-item :delivery="$delivery" />
                @empty
                    <div class="empty-state">Belum ada notifikasi sinkronisasi untuk pesanan ini.</div>
                @endforelse
            </div>
        </article>
    </section>

    <section class="panel-card">
        <div class="panel-heading">
            <div>
                <p class="section-kicker">Catatan Aktivitas</p>
                <h3 class="section-title">Aktivitas sistem untuk transaksi ini</h3>
            </div>
        </div>

        <div class="stack-list">
            @forelse ($paymentOrder->paymentEvents as $event)
                <x-event-item :event="$event" />
            @empty
                <div class="empty-state">Belum ada catatan aktivitas untuk pesanan ini.</div>
            @endforelse
        </div>
    </section>
@endsection