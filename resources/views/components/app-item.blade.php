@props(['application'])

<div class="stack-item">
    <div class="flex items-start justify-between gap-3">
        <div>
            <div class="flex items-center gap-2">
                <p class="stack-title">{{ $application['name'] }}</p>
                <x-status-badge :label="$application['is_active'] ? 'Aktif' : 'Tidak Aktif'"
                    :tone="$application['is_active'] ? 'emerald' : 'slate'" />
            </div>
            <p class="stack-meta">{{ $application['code'] }} &middot; {{ $application['default_provider'] }}</p>
        </div>
        <p class="stack-value">Sukses {{ number_format($application['paid_rate'], 1) }}%</p>
    </div>

    <div class="provider-stats mt-3">
        <div>
            <p class="provider-stat-label">Total Transaksi</p>
            <p class="provider-stat-value">{{ number_format($application['total_orders']) }}</p>
        </div>
        <div>
            <p class="provider-stat-label">Total Nominal</p>
            <p class="provider-stat-value">IDR {{ number_format($application['gross_amount'], 0, ',', '.') }}</p>
        </div>
    </div>

    @if ($application['failed_deliveries'] > 0)
        <p class="stack-meta mt-3">Notifikasi gagal: {{ number_format($application['failed_deliveries']) }}</p>
    @endif
</div>
