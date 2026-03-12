@extends('layouts.admin')

@section('title', $pageTitle)

@section('content')
    <section class="panel-card">
        <form method="GET" class="filter-grid">
            <div class="form-field">
                <label for="application" class="form-label">Application</label>
                <select id="application" name="application" class="form-select">
                    <option value="">All applications</option>
                    @foreach ($applications as $code => $name)
                        <option value="{{ $code }}" @selected(($filters['application'] ?? null) === $code)>{{ $name }}</option>
                    @endforeach
                </select>
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
                <label for="date_from" class="form-label">Date from</label>
                <input id="date_from" type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}" class="form-input">
            </div>
            <div class="form-field">
                <label for="date_to" class="form-label">Date to</label>
                <input id="date_to" type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}" class="form-input">
            </div>
            <div class="form-actions">
                <button type="submit" class="button-primary">Apply filters</button>
                <a href="{{ route('admin.statistics') }}" class="button-link">Reset</a>
            </div>
        </form>
    </section>

    <section class="metric-grid">
        <x-metric-card label="Orders" :value="number_format($summary['total_orders'])" caption="Total payment orders" tone="cyan" />
        <x-metric-card label="Gross volume" :value="'IDR ' . number_format($summary['gross_amount'], 0, ',', '.')" caption="Accumulated order amount" tone="emerald" />
        <x-metric-card label="Paid rate" :value="$summary['total_orders'] > 0 ? number_format(($summary['paid_orders'] / $summary['total_orders']) * 100, 1) . '%' : '0.0%'" caption="Successful orders" tone="violet" />
        <x-metric-card label="Problem rate" :value="$summary['total_orders'] > 0 ? number_format(($summary['problem_orders'] / $summary['total_orders']) * 100, 1) . '%' : '0.0%'" caption="Failed and expired orders" tone="rose" />
    </section>

    <section class="split-grid">
        <article class="panel-card">
            <div class="panel-heading">
                <div>
                    <p class="section-kicker">Status</p>
                    <h3 class="section-title">Breakdown by lifecycle</h3>
                </div>
            </div>
            <div class="stack-list">
                @forelse ($statusBreakdown as $status)
                    <div class="stack-item">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="stack-title">{{ $status->status }}</p>
                                <p class="stack-meta">{{ number_format($status->total_orders) }} orders</p>
                            </div>
                            <p class="stack-value">IDR {{ number_format($status->gross_amount, 0, ',', '.') }}</p>
                        </div>
                    </div>
                @empty
                    <div class="empty-state">Belum ada data statistik.</div>
                @endforelse
            </div>
        </article>

        <article class="panel-card">
            <div class="panel-heading">
                <div>
                    <p class="section-kicker">Trend</p>
                    <h3 class="section-title">Daily volume</h3>
                </div>
            </div>
            <div class="stack-list">
                @forelse ($dailyVolume as $day)
                    <div class="stack-item">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="stack-title">{{ $day->day }}</p>
                                <p class="stack-meta">{{ number_format($day->total_orders) }} orders</p>
                            </div>
                            <p class="stack-value">IDR {{ number_format($day->gross_amount, 0, ',', '.') }}</p>
                        </div>
                    </div>
                @empty
                    <div class="empty-state">Belum ada data harian.</div>
                @endforelse
            </div>
        </article>
    </section>

    <section class="split-grid">
        <article class="panel-card">
            <div class="panel-heading">
                <div>
                    <p class="section-kicker">Providers</p>
                    <h3 class="section-title">Distribution by provider</h3>
                </div>
            </div>
            <div class="stack-list">
                @forelse ($providerBreakdown as $provider)
                    <div class="stack-item">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="stack-title">{{ $provider->provider_code }}</p>
                                <p class="stack-meta">{{ number_format($provider->total_orders) }} orders</p>
                            </div>
                            <p class="stack-value">IDR {{ number_format($provider->gross_amount, 0, ',', '.') }}</p>
                        </div>
                    </div>
                @empty
                    <div class="empty-state">Belum ada distribusi provider.</div>
                @endforelse
            </div>
        </article>

        <article class="panel-card">
            <div class="panel-heading">
                <div>
                    <p class="section-kicker">Applications</p>
                    <h3 class="section-title">Distribution by application</h3>
                </div>
            </div>
            <div class="stack-list">
                @forelse ($applicationBreakdown as $application)
                    <div class="stack-item">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="stack-title">{{ $application->name }}</p>
                                <p class="stack-meta">{{ $application->code }} &middot; {{ number_format($application->total_orders) }} orders</p>
                            </div>
                            <p class="stack-value">IDR {{ number_format($application->gross_amount, 0, ',', '.') }}</p>
                        </div>
                    </div>
                @empty
                    <div class="empty-state">Belum ada distribusi aplikasi.</div>
                @endforelse
            </div>
        </article>
    </section>
@endsection
