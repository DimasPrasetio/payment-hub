@extends('layouts.admin')

@section('title', $pageTitle)

@section('content')
    <x-page-hero :kicker="$pageKicker" :title="$pageHeading" :description="$pageDescription" compact>
        <div class="page-hero-stat">
            <span class="page-hero-label">Tujuan Halaman</span>
            <span class="page-hero-value">Lihat method mapping yang aktif untuk tiap provider, beserta fee dan batas nominal.</span>
        </div>
        <div class="page-hero-stat">
            <span class="page-hero-label">Operator Hint</span>
            <span class="page-hero-value">Mulai dari provider dulu jika ingin memastikan method tertentu memang tersedia.</span>
        </div>
    </x-page-hero>

    <section class="panel-card filter-panel">
        <div class="panel-heading">
            <div>
                <p class="section-kicker">Filter Mapping</p>
                <h3 class="section-title">Temukan metode pembayaran yang ingin dicek</h3>
                <p class="section-copy">Pencarian bisa berdasarkan nama method, internal code, atau kode method dari provider.</p>
            </div>
        </div>

        <form method="GET" class="filter-grid">
            <div class="form-field form-field-wide">
                <label for="q" class="form-label">Search</label>
                <input id="q" name="q" value="{{ $filters['q'] ?? '' }}" class="form-input" placeholder="internal code, provider method code, display name">
            </div>
            <div class="form-field">
                <label for="provider" class="form-label">Provider</label>
                <select id="provider" name="provider" class="form-select">
                    <option value="">All providers</option>
                    @foreach ($providers as $code => $name)
                        <option value="{{ $code }}" @selected(($filters['provider'] ?? null) === $code)>{{ $name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-field">
                <label for="group" class="form-label">Group</label>
                <select id="group" name="group" class="form-select">
                    <option value="">All groups</option>
                    @foreach ($groups as $group)
                        <option value="{{ $group }}" @selected(($filters['group'] ?? null) === $group)>{{ $group }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-field">
                <label for="status" class="form-label">Status</label>
                <select id="status" name="status" class="form-select">
                    <option value="">All statuses</option>
                    <option value="active" @selected(($filters['status'] ?? null) === 'active')>Active</option>
                    <option value="inactive" @selected(($filters['status'] ?? null) === 'inactive')>Inactive</option>
                </select>
            </div>
            <div class="form-actions">
                <button type="submit" class="button-primary">Apply filters</button>
                <a href="{{ route('admin.payment-methods') }}" class="button-link">Reset</a>
            </div>
        </form>
    </section>

    <section class="panel-card">
        <div class="data-table mapping-table">
            <div class="mapping-table-head">
                <span>Metode</span>
                <span>Provider</span>
                <span>Biaya</span>
                <span>Batas Nominal</span>
                <span>Status</span>
            </div>

            <div class="mapping-table-body">
                @forelse ($mappings as $mapping)
                    <article class="mapping-row">
                        <div class="mapping-main">
                            <div class="flex flex-wrap items-center gap-2">
                                <p class="table-primary">{{ $mapping->display_name }}</p>
                                <span class="mapping-chip">
                                    {{ $mapping->group ? Illuminate\Support\Str::of($mapping->group)->replace('_', ' ')->title() : 'Tanpa Grup' }}
                                </span>
                            </div>
                            <div class="mapping-code-list">
                                <span class="mapping-code">Internal: {{ $mapping->internal_code }}</span>
                                <span class="mapping-code">Provider: {{ $mapping->provider_method_code }}</span>
                            </div>
                        </div>

                        <div class="mapping-cell">
                            <p class="mapping-mobile-label">Provider</p>
                            <p class="table-primary">{{ $mapping->paymentProvider?->name ?? $mapping->provider_code }}</p>
                            <p class="table-meta">{{ strtoupper($mapping->provider_code) }}</p>
                        </div>

                        <div class="mapping-cell">
                            <p class="mapping-mobile-label">Biaya</p>
                            <p class="table-primary">Flat IDR {{ number_format($mapping->fee_flat, 0, ',', '.') }}</p>
                            <p class="table-meta">Persen {{ number_format((float) $mapping->fee_percent, 2) }}%</p>
                        </div>

                        <div class="mapping-cell">
                            <p class="mapping-mobile-label">Batas Nominal</p>
                            <p class="table-primary">
                                Min {{ $mapping->min_amount ? 'IDR ' . number_format($mapping->min_amount, 0, ',', '.') : '-' }}
                            </p>
                            <p class="table-meta">
                                Max {{ $mapping->max_amount ? 'IDR ' . number_format($mapping->max_amount, 0, ',', '.') : '-' }}
                            </p>
                        </div>

                        <div class="mapping-status">
                            <x-status-badge :label="$mapping->is_active ? 'Aktif' : 'Tidak Aktif'" :tone="$mapping->is_active ? 'emerald' : 'slate'" />
                        </div>
                    </article>
                @empty
                    <div class="empty-state">Belum ada payment method mapping.</div>
                @endforelse
            </div>
        </div>

        <div class="pagination-wrap">
            {{ $mappings->links() }}
        </div>
    </section>
@endsection
