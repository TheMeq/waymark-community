@props(['event'])

<article {{ $attributes->class('group overflow-hidden rounded-[var(--wm-radius-md)] border border-border bg-surface-raised shadow-[var(--wm-shadow-card)]') }}>
    <a class="block overflow-hidden" href="{{ $event['url'] }}" tabindex="-1" aria-hidden="true">
        <img
            class="aspect-[4/3] w-full object-cover transition-transform duration-300 group-hover:scale-[1.025]"
            src="{{ $event['image_url'] }}"
            alt="{{ $event['image_alt'] }}"
            loading="lazy"
        >
    </a>

    <div class="p-5">
        <h3 class="text-xl text-ink">
            <a class="transition-colors hover:text-brand" href="{{ $event['url'] }}">{{ $event['title'] }}</a>
        </h3>
        <p class="mt-2 text-xs font-semibold uppercase tracking-[0.12em] text-brand">{{ $event['date'] }}</p>

        <x-public.metadata-row
            class="mt-4"
            :items="[
                ['label' => 'Distance', 'value' => $event['distance'], 'symbol' => '↔'],
                ['label' => 'Ascent', 'value' => $event['ascent'], 'symbol' => '↗'],
            ]"
        />

        <div class="mt-4 border-t border-border pt-4">
            <x-public.badge>{{ $event['difficulty'] }}</x-public.badge>
        </div>
    </div>
</article>
