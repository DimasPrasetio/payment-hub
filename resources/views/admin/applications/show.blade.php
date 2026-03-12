@extends('layouts.admin')

@section('title', $pageTitle)

@section('content')
    <section class="metric-grid">
        <x-metric-card label="Total Transaksi" :value="number_format($application->payment_orders_count)"
            caption="Jumlah transaksi dari aplikasi ini" tone="cyan" />
        <x-metric-card label="Total Nominal" :value="'IDR ' . number_format($application->gross_amount ?? 0, 0, ',', '.')"
            caption="Nilai akumulasi transaksi aplikasi ini" tone="emerald" />
        <x-metric-card label="Transaksi Sukses" :value="number_format($application->paid_orders_count)"
            caption="Jumlah transaksi berhasil" tone="violet" />
        <x-metric-card label="Notifikasi Gagal" :value="number_format($application->failed_webhook_deliveries_count)"
            caption="Jumlah notifikasi yang gagal terkirim" tone="rose" />
    </section>

    <section class="detail-grid">
        <article class="panel-card">
            <div class="panel-heading">
                <div>
                    <p class="section-kicker">Konfigurasi</p>
                    <h3 class="section-title">Detail Aplikasi</h3>
                </div>
                <x-status-badge :label="$application->status ? 'Aktif' : 'Tidak Aktif'" :tone="$application->status ? 'emerald' : 'slate'" />
            </div>

            <dl class="description-list">
                <div class="description-item">
                    <dt>Kode Aplikasi</dt>
                    <dd>{{ $application->code }}</dd>
                </div>
                <div class="description-item">
                    <dt>Nama Aplikasi</dt>
                    <dd>{{ $application->name }}</dd>
                </div>
                <div class="description-item">
                    <dt>Default Provider</dt>
                    <dd>{{ $application->defaultProvider?->name ?? $application->default_provider }}</dd>
                </div>
                <div class="description-item">
                    <dt>Webhook URL</dt>
                    <dd>{{ $application->webhook_url }}</dd>
                </div>
            </dl>
        </article>

        <article class="panel-card">
            <div class="panel-heading">
                <div>
                    <p class="section-kicker">Keamanan</p>
                    <h3 class="section-title">Status Credential</h3>
                </div>
            </div>

            <div class="stack-list mt-6">
                <div class="stack-item">
                    <p class="stack-title">API Key</p>
                    <p class="stack-meta">Tersimpan sebagai hash SHA-256. Fingerprint saat ini: {{ $credentialStatus['api_key_fingerprint'] }}</p>
                </div>
                <div class="stack-item">
                    <p class="stack-title">Webhook Secret</p>
                    <p class="stack-meta">Tersimpan terenkripsi. Panjang secret saat ini: {{ $credentialStatus['webhook_secret_length'] }} karakter.</p>
                </div>
            </div>
        </article>
    </section>

    <section class="panel-card">
        <div class="panel-heading">
            <div>
                <p class="section-kicker">Pengelolaan Aplikasi</p>
                <h3 class="section-title">Edit konfigurasi client app</h3>
            </div>
        </div>

        <form method="POST" action="{{ route('admin.applications.update', $application) }}" class="mt-6 space-y-6">
            @csrf
            @method('PUT')
            @include('admin.applications.partials.form', ['submitLabel' => 'Simpan Perubahan', 'application' => $application])
        </form>
    </section>

    <section class="detail-grid">
        <article class="panel-card">
            <div class="panel-heading">
                <div>
                    <p class="section-kicker">Rotasi Credential</p>
                    <h3 class="section-title">Generate ulang secret aplikasi</h3>
                </div>
            </div>

            <div class="stack-list mt-6">
                <div class="stack-item">
                    <p class="stack-title">API Key</p>
                    <p class="stack-meta">Gunakan saat client app perlu credential baru untuk memanggil API orchestrator.</p>
                    <form method="POST" action="{{ route('admin.applications.rotate-api-key', $application) }}" class="mt-4">
                        @csrf
                        <button type="submit" class="button-primary">Generate API Key Baru</button>
                    </form>
                </div>

                <div class="stack-item">
                    <p class="stack-title">Webhook Secret</p>
                    <p class="stack-meta">Gunakan saat client app perlu mengganti secret verifikasi webhook.</p>
                    <form method="POST" action="{{ route('admin.applications.rotate-webhook-secret', $application) }}" class="mt-4">
                        @csrf
                        <button type="submit" class="button-primary">Rotate Webhook Secret</button>
                    </form>
                </div>
            </div>
        </article>

        <article class="panel-card">
            <div class="panel-heading">
                <div>
                    <p class="section-kicker">Danger Zone</p>
                    <h3 class="section-title">Hapus aplikasi</h3>
                </div>
            </div>

            <p class="table-copy mt-6">Aplikasi hanya bisa dihapus jika belum memiliki transaksi dan riwayat webhook.</p>
            <form method="POST" action="{{ route('admin.applications.destroy', $application) }}" class="mt-6" onsubmit="return confirm('Hapus aplikasi ini?');">
                @csrf
                @method('DELETE')
                <button type="submit" class="button-link">Hapus Aplikasi</button>
            </form>
        </article>
    </section>

    <section class="split-grid">
        <article class="panel-card">
            <div class="panel-heading">
                <div>
                    <p class="section-kicker">Daftar Transaksi</p>
                    <h3 class="section-title">Transaksi terbaru dari aplikasi ini</h3>
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
                @forelse ($application->paymentOrders as $payment)
                    <x-payment-row :payment="$payment" />
                @empty
                    <div class="empty-state">Belum ada transaksi untuk aplikasi ini.</div>
                @endforelse
            </div>
        </article>

        <article class="panel-card">
            <div class="panel-heading">
                <div>
                    <p class="section-kicker">Riwayat Sinkronisasi</p>
                    <h3 class="section-title">Notifikasi terbaru ke aplikasi ini</h3>
                </div>
            </div>

            <div class="stack-list">
                @forelse ($application->webhookDeliveries as $delivery)
                    <x-webhook-item :delivery="$delivery" />
                @empty
                    <div class="empty-state">Belum ada riwayat sinkronisasi untuk aplikasi ini.</div>
                @endforelse
            </div>
        </article>
    </section>
@endsection
