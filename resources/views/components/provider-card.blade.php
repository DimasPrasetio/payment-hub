@props(['provider'])

<article class="provider-card">
    <div class="flex items-start justify-between gap-3">
        <div>
            <p class="provider-code">{{ $provider['code'] }}</p>
            <h3 class="provider-name">{{ $provider['name'] }}</h3>
        </div>
        <x-status-badge :label="$provider['health_label']" :tone="$provider['health_tone']" />
    </div>

    <div class="provider-stats">
        <div>
            <p class="provider-stat-label">Lingkungan</p>
            <p class="provider-stat-value">{{ $provider['mode'] === 'production' ? 'Produksi' : 'Uji Coba' }}</p>
        </div>
        <div>
            <p class="provider-stat-label">Aktivitas</p>
            <p class="provider-stat-value">{{ number_format($provider['activity_share'], 1) }}%</p>
        </div>
        <div>
            <p class="provider-stat-label">Tingkat Keberhasilan</p>
            <p class="provider-stat-value">{{ number_format($provider['paid_rate'], 1) }}%</p>
        </div>
        <div>
            <p class="provider-stat-label">Keberhasilan Notifikasi</p>
            <p class="provider-stat-value">
                {{ $provider['webhook_rate'] === null ? 'N/A' : number_format($provider['webhook_rate'], 1) . '%' }}</p>
        </div>
    </div>

    <div class="provider-footer">
        <div>
            <p class="provider-stat-label">Total Transaksi</p>
            <p class="provider-stat-value">{{ number_format($provider['total_orders']) }}</p>
            <p class="provider-stat-sub">IDR {{ number_format($provider['gross_amount'], 0, ',', '.') }}</p>
        </div>
        <div>
            <p class="provider-stat-label">Metode</p>
            <p class="provider-stat-value">{{ $provider['active_methods'] }}/{{ $provider['total_methods'] }}</p>
            <p class="provider-stat-sub">
                {{ $provider['average_settlement_minutes'] ? $provider['average_settlement_minutes'] . ' mnt' : 'Belum ada data' }}
            </p>
        </div>
    </div>
</article>