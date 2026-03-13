@props(['provider'])

<article class="provider-card">
    <div class="flex items-start justify-between gap-3">
        <div>
            <p class="provider-code">{{ $provider['code'] }}</p>
            <h3 class="provider-name">{{ $provider['name'] }}</h3>
            <p class="stack-meta mt-2">{{ strtolower($provider['mode']) === 'production' ? 'Produksi' : 'Uji Coba' }}</p>
        </div>
        <x-status-badge :label="match ($provider['health_label']) {
            'Standby' => 'Siaga',
            'Optimal' => 'Optimal',
            'Watch' => 'Perlu Pantau',
            'Degraded' => 'Menurun',
            default => $provider['health_label'],
        }" :tone="$provider['health_tone']" />
    </div>

    <div class="provider-stats">
        <div>
            <p class="provider-stat-label">Transaksi</p>
            <p class="provider-stat-value">{{ number_format($provider['total_orders']) }}</p>
        </div>
        <div>
            <p class="provider-stat-label">Berhasil</p>
            <p class="provider-stat-value">{{ number_format($provider['paid_rate'], 1) }}%</p>
        </div>
        <div>
            <p class="provider-stat-label">Metode</p>
            <p class="provider-stat-value">{{ $provider['active_methods'] }}/{{ $provider['total_methods'] }}</p>
        </div>
    </div>

    <div class="provider-footer">
        <div>
            <p class="provider-stat-label">Total Nominal</p>
            <p class="provider-stat-value">IDR {{ number_format($provider['gross_amount'], 0, ',', '.') }}</p>
            <p class="provider-stat-sub">Porsi trafik {{ number_format($provider['activity_share'], 1) }}%</p>
        </div>
        <div>
            <p class="provider-stat-label">Info Tambahan</p>
            <p class="provider-stat-value">
                {{ $provider['webhook_rate'] === null ? 'Webhook N/A' : 'Webhook ' . number_format($provider['webhook_rate'], 1) . '%' }}
            </p>
            <p class="provider-stat-sub">
                {{ $provider['average_settlement_minutes'] ? $provider['average_settlement_minutes'] . ' mnt' : 'Belum ada data' }}
            </p>
        </div>
    </div>
</article>
