@extends('layouts.admin')

@section('title', $pageTitle)

@section('content')
    <section class="detail-grid">
        <article class="panel-card">
            <div class="panel-heading">
                <div>
                    <p class="section-kicker">Admin Access</p>
                    <h3 class="section-title">Tambah user admin baru</h3>
                </div>
            </div>

            <form method="POST" action="{{ route('admin.users.store') }}" class="mt-6 space-y-6">
                @csrf
                @include('admin.users.partials.form', ['submitLabel' => 'Buat User Admin'])
            </form>
        </article>

        <article class="panel-card">
            <div class="panel-heading">
                <div>
                    <p class="section-kicker">Catatan Operasional</p>
                    <h3 class="section-title">Akses dan keamanan</h3>
                </div>
            </div>

            <div class="stack-list mt-6">
                <div class="stack-item">
                    <p class="stack-title">Username unik</p>
                    <p class="stack-meta">Username dipakai langsung saat login ke admin panel.</p>
                </div>
                <div class="stack-item">
                    <p class="stack-title">Password terenkripsi</p>
                    <p class="stack-meta">Password disimpan dengan hash dan tidak bisa dilihat kembali setelah dibuat.</p>
                </div>
                <div class="stack-item">
                    <p class="stack-title">Status aktif</p>
                    <p class="stack-meta">User nonaktif tidak dapat login, walaupun password masih benar.</p>
                </div>
            </div>
        </article>
    </section>
@endsection
