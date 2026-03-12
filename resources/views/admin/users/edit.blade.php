@extends('layouts.admin')

@section('title', $pageTitle)

@section('content')
    <section class="detail-grid">
        <article class="panel-card">
            <div class="panel-heading">
                <div>
                    <p class="section-kicker">Admin Access</p>
                    <h3 class="section-title">Edit user admin</h3>
                </div>
                <x-status-badge :label="$managedUser->is_active ? 'Aktif' : 'Tidak Aktif'" :tone="$managedUser->is_active ? 'emerald' : 'slate'" />
            </div>

            <form method="POST" action="{{ route('admin.users.update', $managedUser) }}" class="mt-6 space-y-6">
                @csrf
                @method('PUT')
                @include('admin.users.partials.form', ['submitLabel' => 'Simpan Perubahan', 'managedUser' => $managedUser])
            </form>
        </article>

        <article class="panel-card">
            <div class="panel-heading">
                <div>
                    <p class="section-kicker">Danger Zone</p>
                    <h3 class="section-title">Hapus user admin</h3>
                </div>
            </div>

            <p class="table-copy mt-6">User ini akan kehilangan akses ke admin panel setelah dihapus.</p>
            <form method="POST" action="{{ route('admin.users.destroy', $managedUser) }}" class="mt-6" onsubmit="return confirm('Hapus user admin ini?');">
                @csrf
                @method('DELETE')
                <button type="submit" class="button-link">Hapus User</button>
            </form>
        </article>
    </section>
@endsection
