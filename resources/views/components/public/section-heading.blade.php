@props([
    'eyebrow' => null,
    'title',
    'actionHref' => null,
    'actionLabel' => null,
])

<div {{ $attributes->class('flex items-end justify-between gap-5') }}>
    <div class="max-w-[var(--wm-container-copy)]">
        @if ($eyebrow)
            <p class="mb-2 text-xs font-semibold uppercase tracking-[0.16em] text-brand">{{ $eyebrow }}</p>
        @endif
        <h2 class="text-3xl text-ink sm:text-4xl">{{ $title }}</h2>
    </div>

    @if ($actionHref && $actionLabel)
        <a class="hidden shrink-0 items-center gap-2 text-sm font-semibold text-ink underline decoration-border-strong underline-offset-4 transition-colors hover:text-brand sm:inline-flex" href="{{ $actionHref }}">
            {{ $actionLabel }}
            <span aria-hidden="true">&rarr;</span>
        </a>
    @endif
</div>
