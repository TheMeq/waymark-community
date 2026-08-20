@props(['event'])

@php
    $metadata = array_values(array_filter([
        ['label' => 'Distance', 'value' => $event['distance'] ?? null, 'symbol' => '↔︎'],
        ['label' => 'Ascent', 'value' => $event['ascent'] ?? null, 'symbol' => '↗︎'],
        ['label' => 'Difficulty', 'value' => $event['difficulty'] ?? null, 'symbol' => '▲'],
        ['label' => 'Spaces', 'value' => $event['capacity'] ?? null, 'symbol' => '●'],
    ], fn (array $item): bool => filled($item['value'])));
@endphp

<article {{ $attributes->class('group flex h-full flex-col overflow-hidden rounded-[var(--wm-radius-md)] border border-border bg-surface-raised shadow-[var(--wm-shadow-card)]') }}>
    <a class="relative block overflow-hidden" href="{{ $event['url'] }}" tabindex="-1" aria-hidden="true">
        <img
            class="aspect-[16/6] w-full object-cover transition-transform duration-300 group-hover:scale-[1.025]"
            src="{{ $event['image_url'] }}"
            alt="{{ $event['image_alt'] }}"
            loading="lazy"
        >

        @if (filled($event['day'] ?? null) && filled($event['day_number'] ?? null) && filled($event['month'] ?? null))
            <span class="absolute left-3 top-3 grid min-w-12 rounded-[var(--wm-radius-sm)] bg-brand px-2 py-1 text-center text-on-brand shadow-sm" aria-label="{{ $event['date'] }}">
                <span class="text-[0.6rem] font-semibold uppercase leading-none tracking-[0.1em]" aria-hidden="true">{{ $event['day'] }}</span>
                <span class="text-xl font-semibold leading-none" aria-hidden="true">{{ $event['day_number'] }}</span>
                <span class="text-[0.6rem] font-semibold uppercase leading-none tracking-[0.1em]" aria-hidden="true">{{ $event['month'] }}</span>
            </span>
        @endif
    </a>

    <div class="flex flex-1 flex-col p-3">
        <h3 class="text-base text-ink">
            <a class="transition-colors hover:text-brand" href="{{ $event['url'] }}">{{ $event['title'] }}</a>
        </h3>

        @if (filled($event['location'] ?? null))
            <p class="mt-0.5 text-[0.7rem] text-ink-muted">{{ $event['location'] }}</p>
        @endif

        @if ($metadata !== [])
            <dl class="mt-2 grid grid-cols-2 gap-x-2 gap-y-2 border-t border-border pt-2 lg:grid-cols-4">
                @foreach ($metadata as $item)
                    <div class="min-w-0 text-[0.62rem] leading-tight text-ink-muted">
                        <dt class="flex items-center gap-1 font-medium text-ink">
                            <span class="text-brand" aria-hidden="true">{{ $item['symbol'] }}</span>
                            {{ $item['value'] }}
                        </dt>
                        <dd class="mt-0.5">{{ $item['label'] }}</dd>
                    </div>
                @endforeach
            </dl>
        @endif

        @if (filled($event['leader'] ?? null) || filled($event['status'] ?? null))
            <div class="mt-auto flex min-h-8 flex-wrap items-end justify-between gap-x-2 gap-y-1 border-t border-border pt-2 text-[0.65rem]">
                @if (filled($event['leader'] ?? null))
                    <span class="font-medium text-ink-muted">Led by {{ $event['leader'] }}</span>
                @endif
                @if (filled($event['status'] ?? null))
                    <span class="font-semibold text-brand"><span aria-hidden="true">✓</span> {{ $event['status'] }}</span>
                @endif
            </div>
        @endif
    </div>
</article>
