@extends('layouts.admin')

@section('title', $pageTitle)

@section('content')
    <section class="detail-grid">
        <article class="panel-card">
            <div class="panel-heading">
                <div>
                    <p class="section-kicker">Onboarding Client App</p>
                    <h3 class="section-title">Daftarkan aplikasi baru</h3>
                </div>
            </div>

            <form method="POST" action="{{ route('admin.applications.store') }}" class="mt-6 space-y-6">
                @csrf
                @include('admin.applications.partials.form', ['submitLabel' => 'Buat Aplikasi'])
            </form>
        </article>

        <article class="panel-card">
            <div class="panel-heading">
                <div>
                    <p class="section-kicker">Yang akan dibuat</p>
                    <h3 class="section-title">Credential awal aplikasi</h3>
                </div>
            </div>

            <div class="stack-list mt-6">
                <div class="stack-item">
                    <p class="stack-title">API Key Client</p>
                    <p class="stack-meta">Digunakan oleh client app pada header `X-API-Key` untuk memanggil endpoint `/api/v1/*`.</p>
                </div>
                <div class="stack-item">
                    <p class="stack-title">Webhook Secret</p>
                    <p class="stack-meta">Digunakan client app untuk memverifikasi `X-Webhook-Signature` dari Payment Orchestrator.</p>
                </div>
                <div class="stack-item">
                    <p class="stack-title">Default Provider</p>
                    <p class="stack-meta">Dipakai saat client tidak mengirim override `provider_code` pada create payment. Provider wajib sudah aktif terlebih dahulu.</p>
                </div>
            </div>
        </article>
    </section>
@endsection
