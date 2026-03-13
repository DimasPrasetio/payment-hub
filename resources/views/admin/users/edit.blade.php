@extends('layouts.admin')

@section('title', $pageTitle)

@section('content')
    <x-page-hero :kicker="$pageKicker" :title="$pageHeading" :description="$pageDescription" compact>
        <div class="page-hero-stat">
            <span class="page-hero-label">Status Akun</span>
            <span class="page-hero-value">{{ $managedUser->is_active ? 'Akun aktif dan bisa login ke panel admin.' : 'Akun nonaktif dan tidak bisa login.' }}</span>
        </div>
        <div class="page-hero-stat">
            <span class="page-hero-label">Panduan</span>
            <span class="page-hero-value">Gunakan halaman ini untuk memperbarui identitas akun, password, atau menonaktifkan akses.</span>
        </div>
    </x-page-hero>

    <section class="workspace-grid">
        <div class="workspace-main">
            <article class="panel-card">
                <div class="panel-heading">
                    <div>
                        <p class="section-kicker">Admin Access</p>
                        <h3 class="section-title">Edit user admin</h3>
                        <p class="section-copy">Perbarui identitas akun, password, atau status akses sesuai kebutuhan.</p>
                    </div>
                    <x-status-badge :label="$managedUser->is_active ? 'Aktif' : 'Tidak Aktif'" :tone="$managedUser->is_active ? 'emerald' : 'slate'" />
                </div>

                <form method="POST" action="{{ route('admin.users.update', $managedUser) }}" class="mt-6 space-y-6">
                    @csrf
                    @method('PUT')
                    @include('admin.users.partials.form', ['submitLabel' => 'Simpan Perubahan', 'managedUser' => $managedUser])
                </form>
            </article>
        </div>

        <aside class="workspace-side">
            <article class="panel-card">
                <div class="panel-heading">
                    <div>
                        <p class="section-kicker">Ringkasan</p>
                        <h3 class="section-title">Dampak perubahan akses</h3>
                    </div>
                </div>

                <div class="admin-note-list mt-6">
                    <div class="admin-note-card">
                        <p class="stack-title">Perubahan password</p>
                        <p class="stack-meta">Jika password diganti, user harus menggunakan password baru pada login berikutnya.</p>
                    </div>
                    <div class="admin-note-card">
                        <p class="stack-title">Nonaktifkan akun</p>
                        <p class="stack-meta">Gunakan jika user sementara atau permanen tidak boleh masuk ke panel admin.</p>
                    </div>
                </div>
            </article>

            <details class="disclosure-card">
                <summary class="disclosure-summary">
                    <div>
                        <p class="section-kicker">Danger Zone</p>
                        <p class="disclosure-title">Hapus user admin</p>
                        <p class="disclosure-meta">Gunakan hanya jika akun memang tidak diperlukan lagi.</p>
                    </div>
                    <span class="disclosure-indicator">Lihat</span>
                </summary>

                <div class="disclosure-body">
                    <p class="stack-meta">User ini akan kehilangan akses ke admin panel setelah dihapus.</p>
                    <form method="POST" action="{{ route('admin.users.destroy', $managedUser) }}" class="mt-4" onsubmit="return confirm('Hapus user admin ini?');">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="button-link">Hapus User</button>
                    </form>
                </div>
            </details>
        </aside>
    </section>
@endsection
