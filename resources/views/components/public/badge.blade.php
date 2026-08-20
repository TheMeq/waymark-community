@props([
    'tone' => 'neutral',
])

@php
    $classes = match ($tone) {
        'brand' => 'bg-brand text-on-brand',
        'accent' => 'bg-accent text-on-accent',
        default => 'bg-surface-soft text-ink',
    };
@endphp

<span {{ $attributes->class([$classes, 'inline-flex rounded-[var(--wm-radius-pill)] px-3 py-1 text-xs font-semibold leading-none']) }}>
    {{ $slot }}
</span>
