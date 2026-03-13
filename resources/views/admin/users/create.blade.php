@extends('layouts.admin')

@section('title', $pageTitle)

@section('content')
    <x-page-hero :kicker="$pageKicker" :title="$pageHeading" :description="$pageDescription" compact>
        <div class="page-hero-stat">
            <span class="page-hero-label">Tujuan Halaman</span>
            <span class="page-hero-value">Buat akun admin baru untuk pengguna yang memang perlu akses ke panel operasional.</span>
        </div>
        <div class="page-hero-stat">
            <span class="page-hero-label">Praktik Aman</span>
            <span class="page-hero-value">Berikan akses hanya kepada orang yang memang perlu memantau atau mengubah konfigurasi.</span>
        </div>
    </x-page-hero>

    <section class="workspace-grid">
        <div class="workspace-main">
            <article class="panel-card">
                <div class="panel-heading">
                    <div>
                        <p class="section-kicker">Admin Access</p>
                        <h3 class="section-title">Tambah user admin baru</h3>
                        <p class="section-copy">Masukkan identitas inti dan tentukan apakah akun langsung aktif.</p>
                    </div>
                </div>

                <form method="POST" action="{{ route('admin.users.store') }}" class="mt-6 space-y-6">
                    @csrf
                    @include('admin.users.partials.form', ['submitLabel' => 'Buat User Admin'])
                </form>
            </article>
        </div>

        <aside class="workspace-side">
            <article class="panel-card">
                <div class="panel-heading">
                    <div>
                        <p class="section-kicker">Ringkasan</p>
                        <h3 class="section-title">Hal penting sebelum menyimpan</h3>
                    </div>
                </div>

                <div class="admin-note-list mt-6">
                    <div class="admin-note-card">
                        <p class="stack-title">Username harus unik</p>
                        <p class="stack-meta">Username dipakai saat login dan tidak boleh sama dengan akun lain.</p>
                    </div>
                    <div class="admin-note-card">
                        <p class="stack-title">Status aktif bisa diubah nanti</p>
                        <p class="stack-meta">Akun nonaktif tetap tersimpan, tetapi tidak bisa dipakai masuk ke panel.</p>
                    </div>
                </div>
            </article>
        </aside>
    </section>
@endsection
