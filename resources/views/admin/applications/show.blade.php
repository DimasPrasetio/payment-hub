@extends('layouts.admin')

@section('title', $pageTitle)

@section('content')
    <x-page-hero :kicker="$pageKicker" :title="$pageHeading" :description="$pageDescription">
        <x-slot:actions>
            <a href="{{ route('admin.transactions') }}?application={{ $application->code }}" class="provider-hero-link">Lihat Transaksi</a>
            <a href="{{ route('admin.webhooks') }}?application={{ $application->code }}" class="provider-hero-link">Lihat Webhook</a>
        </x-slot:actions>
    </x-page-hero>

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

    <section class="workspace-grid">
        <div class="workspace-main">
            <article class="panel-card">
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
            </article>

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

                <div class="stack-list mt-6">
                    @forelse ($application->webhookDeliveries as $delivery)
                        <x-webhook-item :delivery="$delivery" />
                    @empty
                        <div class="empty-state">Belum ada riwayat sinkronisasi untuk aplikasi ini.</div>
                    @endforelse
                </div>
            </article>
        </div>

        <aside class="workspace-side">
            <article class="panel-card">
                <div class="panel-heading">
                    <div>
                        <p class="section-kicker">Ringkasan Konfigurasi</p>
                        <h3 class="section-title">Detail aplikasi</h3>
                    </div>
                    <x-status-badge :label="$application->status ? 'Aktif' : 'Tidak Aktif'" :tone="$application->status ? 'emerald' : 'slate'" />
                </div>

                <div class="admin-summary-list mt-6">
                    <div class="admin-summary-item">
                        <p class="admin-summary-label">Kode Aplikasi</p>
                        <p class="admin-summary-value">{{ $application->code }}</p>
                    </div>
                    <div class="admin-summary-item">
                        <p class="admin-summary-label">Default Provider</p>
                        <p class="admin-summary-value">{{ $application->defaultProvider?->name ?? $application->default_provider }}</p>
                    </div>
                    <div class="admin-summary-item">
                        <p class="admin-summary-label">Webhook URL</p>
                        <p class="admin-summary-code">{{ $application->webhook_url }}</p>
                    </div>
                </div>
            </article>

            <details class="disclosure-card">
                <summary class="disclosure-summary">
                    <div>
                        <p class="disclosure-title">Informasi sekunder</p>
                        <p class="disclosure-meta">Status credential, rotasi key, dan penghapusan aplikasi.</p>
                    </div>
                    <span class="disclosure-indicator">Lihat</span>
                </summary>
                <div class="disclosure-body space-y-6">
                    <div>
                        <p class="section-kicker">Keamanan</p>
                        <div class="admin-note-list mt-4">
                            <div class="admin-note-card">
                                <p class="stack-title">API Key</p>
                                <p class="stack-meta">Fingerprint saat ini: {{ $credentialStatus['api_key_fingerprint'] }}</p>
                            </div>
                            <div class="admin-note-card">
                                <p class="stack-title">Webhook Secret</p>
                                <p class="stack-meta">Tersimpan terenkripsi dengan panjang {{ $credentialStatus['webhook_secret_length'] }} karakter.</p>
                            </div>
                        </div>
                    </div>

                    <div>
                        <p class="section-kicker">Rotasi Credential</p>
                        <div class="admin-note-list mt-4">
                            <div class="admin-note-card">
                                <p class="stack-title">API Key</p>
                                <form method="POST" action="{{ route('admin.applications.rotate-api-key', $application) }}" class="mt-4">
                                    @csrf
                                    <button type="submit" class="button-primary">Generate API Key Baru</button>
                                </form>
                            </div>

                            <div class="admin-note-card">
                                <p class="stack-title">Webhook Secret</p>
                                <form method="POST" action="{{ route('admin.applications.rotate-webhook-secret', $application) }}" class="mt-4">
                                    @csrf
                                    <button type="submit" class="button-primary">Buat Webhook Secret Baru</button>
                                </form>
                            </div>
                        </div>
                    </div>

                    <div>
                        <p class="section-kicker">Danger Zone</p>
                        <p class="stack-meta">Aplikasi hanya bisa dihapus jika belum memiliki transaksi dan riwayat webhook.</p>
                        <form method="POST" action="{{ route('admin.applications.destroy', $application) }}" class="mt-4" onsubmit="return confirm('Hapus aplikasi ini?');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="button-link">Hapus Aplikasi</button>
                        </form>
                    </div>
                </div>
            </details>
        </aside>
    </section>
@endsection
