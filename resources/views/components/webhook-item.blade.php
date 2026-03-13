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
    <p class="table-copy mt-3">
        Percobaan {{ $delivery->attempt }} &middot; Respons {{ $delivery->response_code ?? '-' }} &middot;
        Dibuat {{ $delivery->created_at?->format('d M Y H:i') }}
        @if ($delivery->next_retry_at)
            &middot; Diulang {{ $delivery->next_retry_at->format('d M Y H:i') }}
        @endif
    </p>
</div>
