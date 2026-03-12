@props(['label', 'tone' => 'slate'])

@php
$classes = match ($tone) {
    'emerald' => 'text-emerald-700 border-emerald-200 bg-emerald-50',
    'amber' => 'text-amber-700 border-amber-200 bg-amber-50',
    'orange' => 'text-orange-700 border-orange-200 bg-orange-50',
    'rose' => 'text-rose-700 border-rose-200 bg-rose-50',
    'violet' => 'text-violet-700 border-violet-200 bg-violet-50',
    'cyan' => 'text-cyan-700 border-cyan-200 bg-cyan-50',
    default => 'text-slate-600 border-slate-200 bg-slate-50',
};
@endphp

<span {{ $attributes->merge(['class' => "metric-badge {$classes}"]) }}>{{ $label }}</span>
