@extends('layouts.public')

@section('title', $event->title)
@section('meta_description', $event->summary ?? 'Walking holiday details.')

@section('site-header')<x-public.site-header :site="$site" />@endsection

@section('content')
    <article data-print-detail class="bg-surface-raised py-10 sm:py-14">
        <div class="wm-container max-w-[var(--wm-container-copy)]">
            <a class="wm-print-hidden text-sm font-semibold text-brand underline" href="{{ route('holidays.index') }}">&larr; All weekends away</a>
            @if ($holiday['image'])<img class="mt-6 aspect-[16/7] w-full rounded-[var(--wm-radius-lg)] object-cover" src="{{ $holiday['image']['url'] }}" alt="{{ $holiday['image']['alt'] }}">@endif
            <p class="mt-5 text-xs font-semibold uppercase tracking-[0.15em] text-brand">Weekend away</p>
            <h1 class="mt-2 text-4xl text-ink sm:text-5xl">{{ $event->title }}</h1>
            @if ($holiday['status'])<p class="mt-4 inline-flex rounded-[var(--wm-radius-pill)] bg-surface-soft px-3 py-1 text-sm font-semibold text-ink">{{ $holiday['status'] }}</p>@endif
            <p class="mt-4 text-ink-muted"><time datetime="{{ $event->starts_at->toAtomString() }}">{{ $event->starts_at->format('l j F Y') }}</time>@if ($event->ends_at) &ndash; {{ $event->ends_at->format('l j F Y') }}@endif</p>
            <x-account.favourite-control :favourite="$favourite" />
            @auth<p class="wm-print-hidden mt-5"><x-public.button :href="route('community-photos.upload.create', ['event' => $event->id])" variant="secondary">Share photos</x-public.button></p>@endauth
            @if ($event->summary)<p class="mt-6 text-lg text-ink-muted">{{ $event->summary }}</p>@endif
            @if ($event->description)<div class="prose mt-6 max-w-none text-ink">{{ $event->description }}</div>@endif
            <dl class="mt-8 grid gap-4 border-y border-border py-5 sm:grid-cols-2">
                @if ($holiday['destination'])<div><dt class="text-xs font-semibold uppercase tracking-[0.1em] text-ink-muted">Destination</dt><dd class="mt-1 font-semibold text-ink">{{ $holiday['destination'] }}</dd></div>@endif
                @if ($holiday['organiser'])<div><dt class="text-xs font-semibold uppercase tracking-[0.1em] text-ink-muted">Organiser</dt><dd class="mt-1 font-semibold text-ink">{{ $holiday['organiser'] }}</dd></div>@endif
                @if ($holiday['capacity'] !== null)<div><dt class="text-xs font-semibold uppercase tracking-[0.1em] text-ink-muted">Capacity</dt><dd class="mt-1 font-semibold text-ink">{{ $holiday['capacity'] }}</dd></div>@endif
                @if ($holiday['availability'])<div><dt class="text-xs font-semibold uppercase tracking-[0.1em] text-ink-muted">Availability</dt><dd class="mt-1 font-semibold text-ink">{{ $holiday['availability'] }}</dd></div>@endif
            </dl>
            @if ($holiday['accommodation'])<section class="mt-8"><h2 class="text-2xl text-ink">Accommodation</h2><p class="mt-3 text-ink-muted">{{ $holiday['accommodation'] }}</p></section>@endif
            @if ($holiday['pricing'] !== [])<section class="mt-8"><h2 class="text-2xl text-ink">Price</h2>@foreach ($holiday['pricing'] as $line)<p class="mt-2 text-ink-muted">{{ $line }}</p>@endforeach</section>@endif
            @if ($holiday['booking'] !== [])<section class="mt-8"><h2 class="text-2xl text-ink">Booking</h2>@foreach (['status', 'deadline', 'instructions', 'contact'] as $field)@if (filled($holiday['booking'][$field] ?? null))<p class="mt-2 text-ink-muted">{{ $holiday['booking'][$field] }}</p>@endif @endforeach @if (filled($holiday['booking']['url'] ?? null))<a class="mt-3 inline-block font-semibold text-brand underline" href="{{ $holiday['booking']['url'] }}">External booking information</a>@endif</section>@endif
            @if ($holiday['travel'])<section class="mt-8"><h2 class="text-2xl text-ink">Travel</h2><p class="mt-3 text-ink-muted">{{ $holiday['travel'] }}</p></section>@endif
            @if ($holiday['itinerary'] || $holiday['child_itinerary'] !== [])
                <section class="mt-8">
                    <h2 class="text-2xl text-ink">Itinerary</h2>
                    @if ($holiday['itinerary'])<p class="mt-3 text-ink-muted">{{ $holiday['itinerary'] }}</p>@endif
                    @if ($holiday['child_itinerary'] !== [])
                        <ol class="mt-4 grid gap-3">
                            @foreach ($holiday['child_itinerary'] as $item)
                                <li class="rounded-[var(--wm-radius-md)] border border-border bg-surface-soft p-4">
                                    <p class="text-xs font-semibold uppercase tracking-[0.12em] text-brand">{{ $item['type'] }} &middot; {{ $item['date'] }}</p>
                                    <a class="mt-1 inline-block font-semibold text-ink hover:text-brand" href="{{ $item['url'] }}">{{ $item['title'] }}</a>
                                </li>
                            @endforeach
                        </ol>
                    @endif
                </section>
            @endif
            @if (! $gallery->isEmpty())
                <section class="mt-8" aria-labelledby="holiday-gallery-heading">
                    <div class="flex items-end justify-between gap-4">
                        <h2 id="holiday-gallery-heading" class="text-2xl text-ink">Holiday memories</h2>
                        <a class="text-sm font-semibold text-brand underline" href="{{ route('gallery.holidays.show', $event) }}">View all photos</a>
                    </div>
                    <div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        @foreach ($gallery as $photo)
                            <article class="overflow-hidden rounded-[var(--wm-radius-md)] border border-border bg-surface shadow-[var(--wm-shadow-card)]">
                                <a href="{{ $photo->detailUrl }}"><img class="aspect-[4/3] w-full object-cover" src="{{ $photo->imageUrl }}" alt="{{ $photo->caption ?? 'Community photo' }}" loading="lazy" width="{{ $photo->width }}" height="{{ $photo->height }}" style="{{ $photo->rotationStyle }}"></a>
                                @if ($photo->caption)<p class="px-4 pt-3 text-sm font-medium text-ink">{{ $photo->caption }}</p>@endif
                                @if ($photo->contextLabel && $photo->contextUrl)<p class="px-4 pb-3 text-sm text-ink-muted"><a class="underline" href="{{ $photo->contextUrl }}">{{ $photo->contextLabel }}</a></p>@endif
                            </article>
                        @endforeach
                    </div>
                </section>
            @endif
            @if ($holiday['attachments'] !== [])<section class="mt-8"><h2 class="text-2xl text-ink">Downloads</h2><ul class="mt-3 grid gap-2">@foreach ($holiday['attachments'] as $attachment)<li><a class="font-semibold text-brand underline" href="{{ route('holidays.attachment', [$event->slug, $attachment['index']]) }}">{{ $attachment['name'] }}</a></li>@endforeach</ul></section>@endif
        </div>
    </article>
@endsection

@section('site-footer')<x-public.site-footer :site="$site" />@endsection
