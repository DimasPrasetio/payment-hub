@props(['event'])

@php
    $eventType = $event->event_type;
    $category = str_starts_with($eventType, 'callback')
        ? 'callbacks'
        : (str_starts_with($eventType, 'webhook')
            ? 'webhooks'
            : (str_starts_with($eventType, 'provider') ? 'providers' : 'payments'));
    $categoryClass = match ($category) {
        'webhooks' => 'text-violet-700 border-violet-200 bg-violet-50',
        'callbacks' => 'text-amber-700 border-amber-200 bg-amber-50',
        'providers' => 'text-cyan-700 border-cyan-200 bg-cyan-50',
        default => 'text-emerald-700 border-emerald-200 bg-emerald-50',
    };
@endphp

<div class="stack-item">
    <div class="flex items-start justify-between gap-3">
        <div>
            <p class="stack-title">{{ $event->event_type }}</p>
            <p class="stack-meta">{{ $event->paymentOrder?->application?->code }} &middot;
                {{ $event->paymentOrder?->provider_code }} &middot; {{ $event->paymentOrder?->public_id }}</p>
        </div>
        <span
            class="metric-badge {{ $categoryClass }}">{{ match ($category) { 'webhooks' => 'Webhook', 'callbacks' => 'Callback', 'providers' => 'Saluran', default => 'Pembayaran'} }}</span>
    </div>
    <div class="mt-2 flex items-center justify-between gap-3 text-xs text-slate-400">
        <span>{{ $event->paymentOrder?->status?->value ?? '-' }}</span>
        <span>{{ $event->created_at?->format('d M Y H:i') }}</span>
    </div>
</div>