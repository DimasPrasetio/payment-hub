@extends('layouts.admin')

@section('title', $pageTitle)

@section('content')
    <section class="panel-card">
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
        <div class="data-table">
            <div class="table-head table-head-wide">
                <span>Method</span>
                <span>Provider</span>
                <span>Fee</span>
                <span>Amount range</span>
                <span>Status</span>
            </div>

            @forelse ($mappings as $mapping)
                <div class="table-row table-row-wide">
                    <div>
                        <p class="table-primary">{{ $mapping->display_name }}</p>
                        <p class="table-meta">{{ $mapping->internal_code }} &middot; {{ $mapping->provider_method_code }}</p>
                        <p class="table-copy">{{ $mapping->group ?? 'No group' }}</p>
                    </div>
                    <div>
                        <p class="table-primary">{{ $mapping->paymentProvider?->name ?? $mapping->provider_code }}</p>
                        <p class="table-meta">{{ $mapping->provider_code }}</p>
                    </div>
                    <div>
                        <p class="table-primary">IDR {{ number_format($mapping->fee_flat, 0, ',', '.') }}</p>
                        <p class="table-meta">{{ number_format((float) $mapping->fee_percent, 2) }}%</p>
                    </div>
                    <div>
                        <p class="table-primary">{{ $mapping->min_amount ? 'IDR ' . number_format($mapping->min_amount, 0, ',', '.') : '-' }}</p>
                        <p class="table-meta">{{ $mapping->max_amount ? 'IDR ' . number_format($mapping->max_amount, 0, ',', '.') : '-' }}</p>
                    </div>
                    <div>
                        <x-status-badge :label="$mapping->is_active ? 'Active' : 'Inactive'" :tone="$mapping->is_active ? 'emerald' : 'slate'" />
                    </div>
                </div>
            @empty
                <div class="empty-state">Belum ada payment method mapping.</div>
            @endforelse
        </div>

        <div class="pagination-wrap">
            {{ $mappings->links() }}
        </div>
    </section>
@endsection
