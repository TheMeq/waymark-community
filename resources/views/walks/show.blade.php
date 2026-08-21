@extends('layouts.public')

@section('title', $walk['title'])
@section('meta_description', $walk['summary'])

@section('site-header')
    <x-public.site-header class="wm-print-hidden" :site="$site" />
@endsection

@section('content')
    <article class="bg-surface-raised py-10 sm:py-14">
        <div class="wm-container max-w-[var(--wm-container-copy)]">
            <a class="wm-print-hidden text-sm font-semibold text-brand underline decoration-brand/30 underline-offset-4" href="{{ route('walks.index') }}">&larr; All walks</a>
            <p class="text-xs font-semibold uppercase tracking-[0.15em] text-brand">Walk</p>
            <h1 class="mt-2 text-4xl text-ink sm:text-5xl">{{ $walk['title'] }}</h1>
            @if ($walk['status'])
                <p class="mt-4 inline-flex rounded-[var(--wm-radius-pill)] bg-surface-soft px-3 py-1 text-sm font-semibold text-ink">{{ $walk['status'] }}</p>
            @endif
            <p class="mt-4 text-ink-muted"><time datetime="{{ $event->starts_at->toAtomString() }}">{{ $walk['starts_at'] }}</time>@if ($walk['ends_at']) – {{ $walk['ends_at'] }}@endif</p>
            @if ($walk['featured_image'])
                <figure data-walk-featured-image class="mt-6 overflow-hidden rounded-[var(--wm-radius-md)] bg-surface-soft shadow-[var(--wm-shadow-card)]">
                    <img class="aspect-[16/8] w-full object-cover" src="{{ $walk['featured_image']['url'] }}" alt="{{ $walk['featured_image']['alt'] }}">
                </figure>
            @endif
            @if ($walk['updates'] !== [])
                <section class="mt-6 rounded-[var(--wm-radius-md)] border border-border bg-surface-soft p-5" aria-labelledby="updates-heading">
                    <h2 id="updates-heading" class="text-xl text-ink">Updates from the organiser</h2>
                    <ol class="mt-3 grid gap-4">
                        @foreach ($walk['updates'] as $update)
                            <li><time class="text-xs font-semibold uppercase tracking-[0.1em] text-ink-muted">{{ $update['date'] }}</time><p class="mt-1 text-ink">{{ $update['message'] }}</p></li>
                        @endforeach
                    </ol>
                </section>
            @endif
            @if ($walk['recap'] || $walk['highlights'])
                <section class="mt-7 rounded-[var(--wm-radius-md)] border border-border bg-surface p-5" aria-labelledby="recap-heading">
                    <h2 id="recap-heading" class="text-2xl text-ink">Walk recap</h2>
                    @if ($walk['recap'])<div class="prose mt-3 max-w-none text-ink">{!! nl2br(e($walk['recap'])) !!}</div>@endif
                    @if ($walk['highlights'])<h3 class="mt-5 text-lg text-ink">Highlights</h3><p class="mt-2 text-ink-muted">{{ $walk['highlights'] }}</p>@endif
                </section>
                <h2 class="mt-9 text-2xl text-ink">Original walk details</h2>
            @endif
            @if ($walk['summary'])<p class="mt-6 text-lg text-ink-muted">{{ $walk['summary'] }}</p>@endif
            @if ($walk['description'])<div class="prose mt-6 max-w-none text-ink">{!! nl2br(e($walk['description'])) !!}</div>@endif

            <dl class="mt-8 grid gap-4 border-y border-border py-5 sm:grid-cols-2 lg:grid-cols-4">
                @foreach (['distance' => 'Distance', 'ascent' => 'Ascent', 'duration' => 'Estimated duration'] as $key => $label)
                    @if ($walk[$key])<div><dt class="text-xs font-semibold uppercase tracking-[0.1em] text-ink-muted">{{ $label }}</dt><dd class="mt-1 font-semibold text-ink">{{ $walk[$key] }}</dd></div>@endif
                @endforeach
                @if ($walk['grade'])<div style="{!! $walk['grade']['accent_style'] !!}" class="border-l-4 border-[var(--wm-grade-accent,var(--wm-border))] pl-3"><dt class="text-xs font-semibold uppercase tracking-[0.1em] text-ink-muted">Difficulty</dt><dd class="mt-1 font-semibold text-ink">{{ $walk['grade']['name'] }}</dd><dd class="mt-1 text-sm text-ink-muted">{{ $walk['grade']['description'] }}</dd></div>@endif
            </dl>

            @if ($walk['leader_attribution'] || $walk['tags'] !== [])
                <div class="mt-7 grid gap-5 sm:grid-cols-2">
                    @if ($walk['leader_attribution'])
                        <section aria-labelledby="leaders-heading">
                            <h2 id="leaders-heading" class="text-xl text-ink">Walk leaders</h2>
                            <p class="mt-2 text-ink-muted">{!! $walk['leader_attribution'] !!}</p>
                        </section>
                    @endif
                    @if ($walk['tags'] !== [])<section aria-labelledby="tags-heading"><h2 id="tags-heading" class="text-xl text-ink">Walk themes</h2><ul class="mt-2 flex flex-wrap gap-2">@foreach ($walk['tags'] as $tag)<li class="rounded-[var(--wm-radius-pill)] bg-surface-soft px-3 py-1 text-sm text-ink">{{ $tag }}</li>@endforeach</ul></section>@endif
                </div>
            @endif

            @if (isset($walk['sections']['location']))
                <section class="mt-9" aria-labelledby="meeting-heading"><h2 id="meeting-heading" class="text-2xl text-ink">Meeting point</h2><div class="mt-3 rounded-[var(--wm-radius-md)] border border-border bg-surface p-4 text-ink-muted">@foreach (['name', 'address', 'postcode', 'what3words', 'os_grid_reference', 'directions'] as $field)@if (filled($walk['sections']['location'][$field] ?? null))<p>{{ $walk['sections']['location'][$field] }}</p>@endif @endforeach</div></section>
            @endif

            @foreach (['terrain' => 'Terrain', 'parking' => 'Parking', 'toilets' => 'Toilets', 'cafe_pub' => 'Café or pub stop', 'dogs' => 'Dogs', 'accessibility' => 'Accessibility'] as $key => $heading)
                @if (filled($walk['sections'][$key] ?? null))<section class="mt-8" aria-labelledby="{{ $key }}-heading"><h2 id="{{ $key }}-heading" class="text-2xl text-ink">{{ $heading }}</h2><p class="mt-3 text-ink-muted">{{ $walk['sections'][$key] }}</p></section>@endif
            @endforeach

            @if (isset($walk['sections']['public_transport']))
                <section class="mt-8" aria-labelledby="transport-heading"><h2 id="transport-heading" class="text-2xl text-ink">Public transport</h2><div class="mt-3 text-ink-muted"><p>{{ $walk['sections']['public_transport']['station_stop'] ?? null }}</p><p>{{ $walk['sections']['public_transport']['notes'] ?? null }}</p>@if (filled($walk['sections']['public_transport']['url'] ?? null))<a class="wm-print-hidden mt-2 inline-block font-semibold text-brand underline" href="{{ $walk['sections']['public_transport']['url'] }}">Travel information</a>@endif</div></section>
            @endif

            @if (isset($walk['sections']['kit']))
                <section class="mt-8" aria-labelledby="kit-heading"><h2 id="kit-heading" class="text-2xl text-ink">What to bring</h2>@if (filled($walk['sections']['kit']['checklist'] ?? null))<ul class="mt-3 list-inside list-disc text-ink-muted">@foreach ($walk['sections']['kit']['checklist'] as $item)<li>{{ $item }}</li>@endforeach</ul>@endif @if (filled($walk['sections']['kit']['notes'] ?? null))<p class="mt-3 text-ink-muted">{{ $walk['sections']['kit']['notes'] }}</p>@endif</section>
            @endif

            @if (isset($walk['sections']['availability']))<section class="mt-8" aria-labelledby="availability-heading"><h2 id="availability-heading" class="text-2xl text-ink">Walk availability</h2><p class="mt-3 text-ink-muted">{{ $walk['sections']['availability']['status'] ?? null }}</p></section>@endif
            @if ($walk['attachments'] !== [])<section class="wm-print-hidden mt-8" aria-labelledby="downloads-heading"><h2 id="downloads-heading" class="text-2xl text-ink">Downloads</h2><ul class="mt-3 grid gap-2">@foreach ($walk['attachments'] as $attachment)<li><a class="font-semibold text-brand underline" href="{{ route('walks.attachment', [$event->slug, $attachment['index']]) }}">{{ $attachment['name'] }}</a></li>@endforeach</ul></section>@endif
            @if ($walk['has_gpx'])<p class="wm-print-hidden mt-8"><x-public.button href="{{ route('walks.gpx', $event->slug) }}" variant="secondary">Download GPX</x-public.button></p>@endif
            @if ($walk['map'])<div class="wm-print-hidden mt-9"><x-public.walk-map :map="$walk['map']" /></div>@endif
            @if ($relatedWalks->isNotEmpty())
                <section class="mt-12" aria-labelledby="related-walks-heading">
                    <h2 id="related-walks-heading" class="text-2xl text-ink">Related walks</h2>
                    <div class="mt-5 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">@foreach ($relatedWalks as $relatedWalk)<x-public.event-card :event="$relatedWalk" />@endforeach</div>
                </section>
            @endif
        </div>
    </article>
@endsection

@section('site-footer')
    <x-public.site-footer class="wm-print-hidden" :site="$site" />
@endsection
