@props([
    'kicker' => null,
    'title' => null,
    'description' => null,
    'compact' => false,
])

@php
    $hasActions = isset($actions) && trim((string) $actions) !== '';
    $hasBody = ! $compact && trim((string) $slot) !== '';
@endphp

@if ($hasActions || $hasBody)
    <section {{ $attributes->class(['page-hero', 'page-hero-compact' => $compact]) }}>
        @if ($hasActions)
            <div class="page-hero-header">
                <div class="page-hero-actions">
                    {{ $actions }}
                </div>
            </div>
        @endif

        @if ($hasBody)
            <div class="page-hero-body">
                {{ $slot }}
            </div>
        @endif
    </section>
@endif
