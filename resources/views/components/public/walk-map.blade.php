@props(['map' => null])

@if (is_array($map)
    && filled($map['tile_url'] ?? null)
    && filled($map['attribution'] ?? null)
    && (filled($map['meeting_point'] ?? null) || filled($map['route_points'] ?? null)))
    <section
        x-data="walkMap(@js($map))"
        x-init="initialise()"
        class="overflow-hidden rounded-2xl border border-ink/10 bg-surface shadow-sm"
    >
        <div x-ref="canvas" class="h-80 w-full" role="region" aria-label="Walk map"></div>
        <p class="px-4 py-3 text-sm text-ink-muted">Use the meeting-point details above for directions.</p>
    </section>
@endif
