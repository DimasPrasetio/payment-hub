@extends('layouts.admin')

@section('title', $pageTitle)

@section('content')
    <section class="panel-card">
        <form method="GET" class="filter-grid">
            <div class="form-field form-field-wide">
                <label for="q" class="form-label">Cari User</label>
                <input id="q" name="q" value="{{ $filters['q'] ?? '' }}" class="form-input"
                    placeholder="Username, nama, atau email">
            </div>
            <div class="form-field">
                <label for="status" class="form-label">Status</label>
                <select id="status" name="status" class="form-select">
                    <option value="">Semua Status</option>
                    <option value="active" @selected(($filters['status'] ?? null) === 'active')>Aktif</option>
                    <option value="inactive" @selected(($filters['status'] ?? null) === 'inactive')>Tidak Aktif</option>
                </select>
            </div>
            <div class="form-actions">
                <button type="submit" class="button-primary">Terapkan Filter</button>
                <a href="{{ route('admin.users.index') }}" class="button-link">Reset Filter</a>
                <a href="{{ route('admin.users.create') }}" class="button-link">Tambah User</a>
            </div>
        </form>
    </section>

    <section class="card-grid">
        @forelse ($usersList as $user)
            <article class="panel-card">
                <div class="panel-heading">
                    <div>
                        <p class="section-kicker">{{ $user->username }}</p>
                        <h3 class="section-title">{{ $user->name }}</h3>
                    </div>
                    <x-status-badge :label="$user->is_active ? 'Aktif' : 'Tidak Aktif'" :tone="$user->is_active ? 'emerald' : 'slate'" />
                </div>

                <dl class="description-list mt-6">
                    <div class="description-item">
                        <dt>Email</dt>
                        <dd>{{ $user->email ?: '-' }}</dd>
                    </div>
                    <div class="description-item">
                        <dt>Dibuat</dt>
                        <dd>{{ $user->created_at?->format('d M Y H:i') }}</dd>
                    </div>
                    <div class="description-item">
                        <dt>Terakhir Diperbarui</dt>
                        <dd>{{ $user->updated_at?->format('d M Y H:i') }}</dd>
                    </div>
                </dl>

                <a href="{{ route('admin.users.edit', $user) }}" class="button-link mt-6">Kelola User</a>
            </article>
        @empty
            <div class="empty-state">Belum ada user admin yang terdaftar.</div>
        @endforelse
    </section>

    <div class="pagination-wrap">
        {{ $usersList->links() }}
    </div>
@endsection
