@props(['status'])

<article class="status-card">
    <div class="flex items-start justify-between gap-3">
        <div>
            <p class="metric-label">{{ $status['label'] }}</p>
            <p class="status-count">{{ number_format($status['count']) }}</p>
        </div>
        <x-status-badge :label="number_format($status['ratio'], 1) . '%'" :tone="$status['tone']" />
    </div>
    <p class="status-amount">IDR {{ number_format($status['amount'], 0, ',', '.') }}</p>
    <div class="status-track">
        <div class="status-bar {{ $status['tone'] }}" style="width: {{ min(100, $status['ratio']) }}%"></div>
    </div>
</article>
