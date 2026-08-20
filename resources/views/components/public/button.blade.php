@props([
    'href' => null,
    'variant' => 'primary',
    'type' => 'button',
])

@php
    $classes = match ($variant) {
        'secondary' => 'border border-border bg-surface-raised text-ink hover:border-[var(--wm-border-strong)] hover:bg-surface-soft',
        'quiet' => 'text-ink hover:bg-surface-soft',
        default => 'border border-brand bg-brand text-on-brand hover:brightness-95',
    };

    $classes .= ' inline-flex min-h-11 items-center justify-center gap-2 rounded-[var(--wm-radius-pill)] px-5 py-2.5 text-sm font-semibold no-underline transition-[background-color,border-color,filter] duration-150';
@endphp

@if ($href)
    <a {{ $attributes->class($classes) }} href="{{ $href }}">{{ $slot }}</a>
@else
    <button {{ $attributes->class($classes) }} type="{{ $type }}">{{ $slot }}</button>
@endif
