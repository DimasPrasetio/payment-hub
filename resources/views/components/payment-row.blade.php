@props(['payment'])

<article class="table-row">
    <div>
        <a href="{{ route('admin.transactions.show', $payment) }}"
            class="table-primary-link">{{ $payment->public_id }}</a>
        <p class="table-meta">{{ $payment->application?->code }} &middot; {{ $payment->external_order_id }}</p>
        <p class="table-copy">Dibuat {{ $payment->created_at?->format('d M Y H:i') }}</p>
    </div>
    <div>
        <p class="table-primary">{{ $payment->customer_name }}</p>
        <p class="table-meta">{{ $payment->payment_method }}</p>
        @if ($payment->latestProviderTransaction?->pay_code)
            <p class="table-copy">{{ $payment->latestProviderTransaction->pay_code }}</p>
        @endif
    </div>
    <div>
        <p class="table-primary">{{ $payment->provider_code }}</p>
        <p class="table-copy">
            @if ($payment->paid_at)
                Dibayar {{ $payment->paid_at->format('d M Y H:i') }}
            @elseif ($payment->expires_at)
                Kedaluwarsa {{ $payment->expires_at->format('d M Y H:i') }}
            @else
                Menunggu penyelesaian
            @endif
        </p>
    </div>
    <div class="table-primary">IDR {{ number_format($payment->amount, 0, ',', '.') }}</div>
    <div>
        <x-status-badge :label="$payment->status?->value ?? (string) $payment->status" :tone="$payment->status?->tone() ?? 'slate'" />
    </div>
</article>