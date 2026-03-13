@extends('layouts.admin')

@section('title', $pageTitle)

@section('content')
    <x-page-hero :kicker="$pageKicker" :title="$pageHeading" :description="$pageDescription" compact>
        <div class="page-hero-stat">
            <span class="page-hero-label">Yang Akan Dibuat</span>
            <span class="page-hero-value">Satu koneksi aplikasi lengkap dengan API key dan webhook secret awal.</span>
        </div>
        <div class="page-hero-stat">
            <span class="page-hero-label">Bantuan Operator</span>
            <span class="page-hero-value">Isi identitas aplikasi, pilih provider default, lalu tentukan webhook URL.</span>
        </div>
    </x-page-hero>

    <section class="workspace-grid">
        <div class="workspace-main">
            <article class="panel-card">
                <div class="panel-heading">
                    <div>
                        <p class="section-kicker">Onboarding Client App</p>
                        <h3 class="section-title">Daftarkan aplikasi baru</h3>
                        <p class="section-copy">Isi identitas inti, pilih provider default, lalu tentukan alamat webhook.</p>
                    </div>
                </div>

                <form method="POST" action="{{ route('admin.applications.store') }}" class="mt-6 space-y-6">
                    @csrf
                    @include('admin.applications.partials.form', ['submitLabel' => 'Buat Aplikasi'])
                </form>
            </article>
        </div>

        <aside class="workspace-side">
            <article class="panel-card">
                <div class="panel-heading">
                    <div>
                        <p class="section-kicker">Ringkasan</p>
                        <h3 class="section-title">Yang perlu disiapkan</h3>
                    </div>
                </div>

                <div class="admin-note-list mt-6">
                    <div class="admin-note-card">
                        <p class="stack-title">Credential dibuat otomatis</p>
                        <p class="stack-meta">API key dan webhook secret akan tersedia setelah aplikasi disimpan.</p>
                    </div>
                    <div class="admin-note-card">
                        <p class="stack-title">Provider default bersifat fallback</p>
                        <p class="stack-meta">Digunakan saat aplikasi tidak memilih provider secara manual.</p>
                    </div>
                    <div class="admin-note-card">
                        <p class="stack-title">Webhook harus valid</p>
                        <p class="stack-meta">Alamat ini dipakai untuk mengirim pembaruan status pembayaran ke aplikasi.</p>
                    </div>
                </div>
            </article>
        </aside>
    </section>
@endsection
