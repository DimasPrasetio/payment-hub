@props(['delivery'])

<div class="stack-item">
    <div class="flex items-start justify-between gap-3">
        <div>
            <p class="stack-title">{{ $delivery->event_type }}</p>
            <p class="stack-meta">{{ $delivery->paymentOrder?->application?->code ?? $delivery->application?->code }}
                &middot; {{ $delivery->paymentOrder?->public_id }}</p>
        </div>
        <x-status-badge :label="$delivery->status?->value ?? (string) $delivery->status"
            :tone="$delivery->status?->tone() ?? 'slate'" />
    </div>
    <div class="mt-3 grid grid-cols-2 gap-3 text-xs text-slate-500">
        <div>
            <p class="provider-stat-label">Percobaan</p>
            <p class="provider-stat-value">{{ $delivery->attempt }}</p>
        </div>
        <div>
            <p class="provider-stat-label">Respons</p>
            <p class="provider-stat-value">{{ $delivery->response_code ?? '-' }}</p>
        </div>
    </div>
    <p class="table-copy mt-3">
        Dibuat {{ $delivery->created_at?->format('d M Y H:i') }}
        @if ($delivery->next_retry_at)
            · Diulang {{ $delivery->next_retry_at->format('d M Y H:i') }}
        @endif
    </p>
</div>