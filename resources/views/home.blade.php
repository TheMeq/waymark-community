@extends('layouts.public')

@section('title', $homepage->site['name'])
@section('meta_description', $homepage->hero['summary'])

@section('site-header')
    <x-public.site-header :site="$homepage->site" />
@endsection

@section('content')
    <section class="wm-hero relative isolate overflow-hidden bg-surface-raised">
        <img
            class="absolute inset-0 -z-20 size-full object-cover object-[68%_center]"
            src="{{ $homepage->hero['image_url'] }}"
            alt="{{ $homepage->hero['image_alt'] }}"
            fetchpriority="high"
        >
        <div class="wm-hero-shade absolute inset-0 -z-10"></div>

        <div class="wm-container flex min-h-[34rem] items-center py-10 sm:min-h-[38rem] lg:min-h-[22rem] lg:py-6">
            <div class="max-w-[44rem] pt-24 sm:pt-14 lg:pt-0">
                <p class="mb-4 text-xs font-semibold uppercase tracking-[0.17em] text-brand">{{ $homepage->hero['eyebrow'] }}</p>
                <h1 class="text-[clamp(2.75rem,5vw,3.6rem)] text-ink">
                    {{ $homepage->hero['headline'] }}
                    <span class="block text-brand">{{ $homepage->hero['highlight'] }}</span>
                </h1>
                <p class="mt-4 max-w-lg text-base leading-relaxed text-ink">{{ $homepage->hero['summary'] }}</p>

                <div class="mt-5 flex flex-wrap gap-3">
                    <x-public.button href="/walks">Upcoming walks <span aria-hidden="true">&rarr;</span></x-public.button>
                    <x-public.button href="/join" variant="secondary">Join us</x-public.button>
                </div>

                <ul class="mt-6 grid gap-3 border-t border-ink/10 pt-4 sm:grid-cols-3 lg:max-w-[42rem]" aria-label="Community highlights">
                    @foreach ($homepage->benefits as $benefit)
                        <li class="flex items-center gap-3">
                            <span class="grid size-10 shrink-0 place-items-center rounded-full border border-brand/30 bg-white/70 text-brand" aria-hidden="true">
                                @if ($benefit['symbol'] === 'people')
                                    <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M16 20v-1.5a4.5 4.5 0 0 0-4.5-4.5h-3A4.5 4.5 0 0 0 4 18.5V20M10 10a3 3 0 1 0 0-6 3 3 0 0 0 0 6Zm6.5 1a2.5 2.5 0 1 0 0-5M18 20v-1.5a4.5 4.5 0 0 0-2.4-4" stroke-linecap="round" /></svg>
                                @elseif ($benefit['symbol'] === 'route')
                                    <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="m3 19 6-12 4 7 2-4 6 9H3Z" stroke-linejoin="round" /></svg>
                                @else
                                    <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M5 4v3M19 4v3M4 9h16M6 6h12a2 2 0 0 1 2 2v11H4V8a2 2 0 0 1 2-2Z" stroke-linecap="round" /><path d="M8 13h3v3H8z" /></svg>
                                @endif
                            </span>
                            <span class="leading-tight">
                                <strong class="block text-xs text-ink">{{ $benefit['title'] }}</strong>
                                <span class="text-[0.7rem] text-ink-muted">{{ $benefit['detail'] }}</span>
                            </span>
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>
    </section>

    <section class="bg-surface-raised py-7 sm:py-8" aria-labelledby="weekend-heading">
        <div class="wm-container grid gap-10 lg:grid-cols-[minmax(0,2.45fr)_minmax(19rem,1fr)]">
            <div class="min-w-0">
                <div class="flex items-end justify-between gap-4">
                    <h2 id="weekend-heading" class="text-3xl text-ink">This Weekend</h2>
                    <a class="text-sm font-semibold text-brand underline decoration-brand/30 underline-offset-4 sm:hidden" href="/walks">View all</a>
                </div>
                <div class="wm-card-rail mt-5 grid gap-4">
                    @foreach ($homepage->weekendWalks as $walk)
                        <x-public.event-card :event="$walk" />
                    @endforeach
                </div>
            </div>

            <div>
                <div class="flex items-end justify-between gap-4">
                    <h2 class="text-2xl text-ink">Holidays &amp; Weekends Away</h2>
                    <a class="shrink-0 text-sm font-semibold text-brand" href="/weekends">View all <span aria-hidden="true">&rarr;</span></a>
                </div>
                <article class="group mt-5 overflow-hidden rounded-[var(--wm-radius-md)] border border-border bg-surface-raised shadow-[var(--wm-shadow-card)] md:grid md:grid-cols-[1.55fr_1fr] lg:block">
                    <div class="relative overflow-hidden">
                        <img class="aspect-[16/7] w-full object-cover transition-transform duration-300 group-hover:scale-[1.025] md:h-full md:min-h-56 md:object-cover lg:aspect-[16/7] lg:min-h-0" src="{{ $homepage->holiday['image_url'] }}" alt="{{ $homepage->holiday['image_alt'] }}" loading="lazy">
                        <x-public.badge class="absolute left-4 top-4" tone="brand">{{ $homepage->holiday['duration'] }}</x-public.badge>
                    </div>
                    <div class="p-3">
                        <h3 class="text-lg"><a class="hover:text-brand" href="{{ $homepage->holiday['url'] }}">{{ $homepage->holiday['title'] }}</a></h3>
                        <p class="mt-1 text-sm text-ink-muted">{{ $homepage->holiday['location'] }}</p>
                        <p class="mt-2 text-xs font-medium text-ink">{{ $homepage->holiday['date'] }}</p>
                        <p class="mt-2 text-xs text-ink-muted">{{ $homepage->holiday['summary'] }}</p>
                    </div>
                </article>
            </div>
        </div>
    </section>

    <section class="border-y border-border bg-surface py-6" aria-labelledby="gallery-heading">
        <div class="wm-container wm-photo-band grid gap-4">
            <div class="flex flex-col justify-center pb-2 lg:pb-0">
                <p class="text-xs font-semibold uppercase tracking-[0.15em] text-brand">Our community</p>
                <h2 id="gallery-heading" class="mt-2 text-3xl">Photos from our walks &amp; holidays</h2>
                <p class="mt-2 text-xs text-ink-muted">Moments worth sharing.</p>
                <a class="mt-3 text-sm font-semibold text-brand" href="/photos">View gallery <span aria-hidden="true">&rarr;</span></a>
            </div>

            @foreach ($homepage->gallery as $index => $photo)
                <figure class="{{ $index === 0 ? 'wm-photo-feature' : '' }} h-36 self-center overflow-hidden rounded-[var(--wm-radius-md)] bg-surface-soft lg:h-32">
                    <img class="size-full object-cover transition-transform duration-300 hover:scale-[1.025]" src="{{ $photo['image_url'] }}" alt="{{ $photo['image_alt'] }}" loading="lazy">
                </figure>
            @endforeach
        </div>
    </section>

    <section class="bg-surface-raised py-6 sm:py-7">
        <div class="wm-container grid gap-5 lg:grid-cols-[minmax(0,3fr)_minmax(18rem,1fr)]">
            <div class="rounded-[var(--wm-radius-md)] bg-surface-soft p-4 sm:p-5">
                <div class="grid gap-5 md:grid-cols-[minmax(13rem,1fr)_2fr_auto] md:items-center">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.15em] text-brand">New here?</p>
                        <h2 class="mt-2 text-3xl">Join us!</h2>
                        <p class="mt-3 text-sm text-ink-muted">A friendly, volunteer-led group for people who enjoy the outdoors.</p>
                        <blockquote class="mt-3 text-xs italic text-ink-muted">{{ $homepage->testimonial }}</blockquote>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-3">
                        <p class="border-l-2 border-brand/25 pl-4 text-sm"><strong class="block">Everyone welcome</strong><span class="text-ink-muted">Come as you are.</span></p>
                        <p class="border-l-2 border-brand/25 pl-4 text-sm"><strong class="block">Try a walk</strong><span class="text-ink-muted">Find your pace.</span></p>
                        <p class="border-l-2 border-brand/25 pl-4 text-sm"><strong class="block">Good company</strong><span class="text-ink-muted">Share the day.</span></p>
                    </div>

                    <x-public.button href="/join">Join the group</x-public.button>
                </div>
            </div>

            <aside class="rounded-[var(--wm-radius-md)] border border-border bg-surface p-4" aria-labelledby="resources-heading">
                <h2 id="resources-heading" class="text-xl">Member resources</h2>
                <ul class="mt-2 divide-y divide-border text-xs">
                    @foreach ($homepage->memberResources as $resource)
                        <li><a class="flex min-h-8 items-center justify-between gap-3 py-1.5 hover:text-brand" href="{{ $resource['url'] }}"><span>{{ $resource['label'] }}</span><span aria-hidden="true">&rarr;</span></a></li>
                    @endforeach
                </ul>
            </aside>
        </div>
    </section>
@endsection

@section('site-footer')
    <x-public.site-footer :site="$homepage->site" />
@endsection
