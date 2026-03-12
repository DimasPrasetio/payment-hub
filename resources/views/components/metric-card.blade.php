@props(['label', 'value', 'caption', 'tone' => 'slate'])

<article class="metric-card metric-card-{{ $tone }}">
    <p class="metric-label">{{ $label }}</p>
    <p class="metric-value">{{ $value }}</p>
    <p class="metric-caption">{{ $caption }}</p>
</article>
