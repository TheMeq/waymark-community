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
            @if ($walk['summary'])<p class="mt-6 text-lg text-ink-muted">{{ $walk['summary'] }}</p>@endif
            @if ($walk['description'])<div class="prose mt-6 max-w-none text-ink">{!! nl2br(e($walk['description'])) !!}</div>@endif

            <dl class="mt-8 grid gap-4 border-y border-border py-5 sm:grid-cols-2 lg:grid-cols-4">
                @foreach (['distance' => 'Distance', 'ascent' => 'Ascent', 'duration' => 'Estimated duration'] as $key => $label)
                    @if ($walk[$key])<div><dt class="text-xs font-semibold uppercase tracking-[0.1em] text-ink-muted">{{ $label }}</dt><dd class="mt-1 font-semibold text-ink">{{ $walk[$key] }}</dd></div>@endif
                @endforeach
                @if ($walk['grade'])<div><dt class="text-xs font-semibold uppercase tracking-[0.1em] text-ink-muted">Difficulty</dt><dd class="mt-1 font-semibold text-ink">{{ $walk['grade']['name'] }}</dd><dd class="mt-1 text-sm text-ink-muted">{{ $walk['grade']['description'] }}</dd></div>@endif
            </dl>

            @if ($walk['leaders'] !== [] || $walk['tags'] !== [])
                <div class="mt-7 grid gap-5 sm:grid-cols-2">
                    @if ($walk['leaders'] !== [])<section aria-labelledby="leaders-heading"><h2 id="leaders-heading" class="text-xl text-ink">Walk leaders</h2><p class="mt-2 text-ink-muted">{{ implode(', ', $walk['leaders']) }}</p></section>@endif
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
            @if ($walk['has_gpx'])<p class="wm-print-hidden mt-8"><x-public.button href="{{ route('walks.gpx', $event->slug) }}" variant="secondary">Download GPX</x-public.button></p>@endif
            @if ($walk['map'])<div class="wm-print-hidden mt-9"><x-public.walk-map :map="$walk['map']" /></div>@endif
        </div>
    </article>
@endsection

@section('site-footer')
    <x-public.site-footer class="wm-print-hidden" :site="$site" />
@endsection
