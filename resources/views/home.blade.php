@extends('layouts.public')

@section('title', $homepage->site['name'])
@section('meta_description', $homepage->hero['summary'])

@section('site-header')
    <x-public.site-header :site="$homepage->site" />
@endsection

@section('content')
    <div class="flex flex-col">
    @if ($homepageSections->has('hero'))
    <section data-homepage-section="hero" data-layout="{{ $homepageSections['hero']->layout_variant }}" class="wm-hero wm-home-layout-{{ $homepageSections['hero']->layout_variant }} relative isolate overflow-hidden bg-surface-raised" style="order: {{ $homepageSections['hero']->sort_order }}">
        <img
            class="absolute inset-0 -z-20 size-full object-cover object-[68%_center]"
            src="{{ $homepage->hero['image_url'] }}"
            alt="{{ $homepage->hero['image_alt'] }}"
            fetchpriority="high"
        >
        <div class="wm-hero-shade absolute inset-0 -z-10"></div>

        <div class="wm-container flex min-h-[34rem] items-center py-10 sm:min-h-[38rem] lg:min-h-[20rem] lg:py-4">
            <div class="w-full min-w-0 max-w-[44rem] pt-24 sm:pt-14 lg:max-w-[40rem] lg:pt-0">
                <p class="mb-4 text-xs font-semibold uppercase tracking-[0.17em] text-brand lg:mb-2">{{ $homepage->hero['eyebrow'] }}</p>
                <h1 class="text-[clamp(2.75rem,5vw,3.6rem)] text-ink lg:text-[3.15rem]">
                    {{ $homepage->hero['headline'] }}
                    <span class="block text-brand">{{ $homepage->hero['highlight'] }}</span>
                </h1>
                <p class="mt-4 max-w-lg text-base leading-relaxed text-ink lg:mt-2 lg:max-w-md lg:text-sm lg:leading-normal">{{ $homepage->hero['summary'] }}</p>

                <div class="mt-5 flex flex-wrap gap-3 lg:mt-3">
                    <x-public.button :href="$homepageSections['hero']->cta_url ?: route('walks.index')">{{ $homepageSections['hero']->cta_label ?: 'Upcoming walks' }} <span aria-hidden="true">&rarr;</span></x-public.button>
                    <x-public.button :href="route('new-here')" variant="secondary">Join us</x-public.button>
                </div>

                <ul class="mt-6 grid gap-3 border-t border-ink/10 pt-4 sm:grid-cols-3 lg:mt-4 lg:max-w-[38rem] lg:gap-2 lg:pt-3" aria-label="Community highlights">
                    @foreach ($homepage->benefits as $benefit)
                        <li class="flex items-center gap-3 lg:gap-2">
                            <span class="grid size-10 shrink-0 place-items-center rounded-full border border-brand/30 bg-white/70 text-brand lg:size-8" aria-hidden="true">
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
    @endif

    @if ($homepageSections->has('whats_on'))
    <section data-homepage-section="whats_on" data-layout="{{ $homepageSections['whats_on']->layout_variant }}" class="wm-home-layout-{{ $homepageSections['whats_on']->layout_variant }} bg-surface-raised py-7 sm:py-8 lg:py-5" aria-labelledby="weekend-heading" style="order: {{ $homepageSections['whats_on']->sort_order }}">
        <div class="wm-container grid gap-10 {{ $homepageSections['whats_on']->layout_variant === 'walks_first' ? 'lg:grid-cols-[minmax(0,3fr)_minmax(17rem,1fr)]' : 'lg:grid-cols-[minmax(0,2.45fr)_minmax(19rem,1fr)]' }} lg:gap-6">
            <div class="min-w-0">
                <div class="flex items-end justify-between gap-4">
                    <h2 id="weekend-heading" class="text-3xl text-ink lg:text-2xl">{{ $homepageSections['whats_on']->heading ?: 'This Weekend' }}</h2>
                    <a class="text-sm font-semibold text-brand underline decoration-brand/30 underline-offset-4 sm:hidden" href="{{ $homepageSections['whats_on']->cta_url ?: route('walks.index') }}">{{ $homepageSections['whats_on']->cta_label ?: 'View all' }}</a>
                </div>
                @if (filled($homepageSections['whats_on']->supporting_copy))<p class="mt-2 text-sm text-ink-muted">{{ $homepageSections['whats_on']->supporting_copy }}</p>@endif
                @if ($homepage->weekendWalks !== [])
                    <div class="wm-card-rail mt-5 grid gap-4 lg:mt-3 lg:gap-3">
                        @foreach ($homepage->weekendWalks as $walk)
                            <x-public.event-card :event="$walk" />
                        @endforeach
                    </div>
                @else
                    <p class="mt-5 text-sm text-ink-muted">There are no upcoming walks to show right now.</p>
                @endif
            </div>

            @if (! $configuredSectionKeys->contains('holiday'))
            <div>
                <div class="flex flex-wrap items-end justify-between gap-4">
                    <h2 class="text-2xl text-ink lg:text-xl">Holidays &amp; Weekends Away</h2>
                    <a class="text-sm font-semibold text-brand lg:text-xs" href="{{ route('holidays.index') }}">View all <span aria-hidden="true">&rarr;</span></a>
                </div>
                @if ($homepage->holiday !== [])<article class="group mt-5 overflow-hidden rounded-[var(--wm-radius-md)] border border-border bg-surface-raised shadow-[var(--wm-shadow-card)] md:grid md:grid-cols-[1.55fr_1fr] lg:mt-3 lg:block">
                    <div class="relative overflow-hidden">
                        <img class="aspect-[16/7] w-full object-cover transition-transform duration-300 group-hover:scale-[1.025] md:h-full md:min-h-56 md:object-cover lg:aspect-[16/6] lg:min-h-0" src="{{ $homepage->holiday['image_url'] }}" alt="{{ $homepage->holiday['image_alt'] }}" loading="lazy">
                        <x-public.badge class="absolute left-4 top-4" tone="brand">{{ $homepage->holiday['duration'] }}</x-public.badge>
                    </div>
                    <div class="p-3">
                        <h3 class="text-lg lg:text-base"><a class="hover:text-brand" href="{{ $homepage->holiday['url'] }}">{{ $homepage->holiday['title'] }}</a></h3>
                        <p class="mt-1 text-sm text-ink-muted lg:text-xs">{{ $homepage->holiday['location'] }}</p>
                        <p class="mt-2 text-xs font-medium text-ink lg:mt-1">{{ $homepage->holiday['date'] }}</p>
                        <p class="mt-2 text-xs text-ink-muted lg:mt-1">{{ $homepage->holiday['summary'] }}</p>
                    </div>
                </article>@endif
            </div>
            @endif
        </div>
    </section>
    @endif

    @if ($homepageSections->has('gallery') && ($homepage->gallery !== [] || $homepageSections['gallery']->empty_behavior === 'message'))
    <section data-homepage-section="gallery" data-layout="{{ $homepageSections['gallery']->layout_variant }}" class="wm-home-layout-{{ $homepageSections['gallery']->layout_variant }} border-y border-border bg-surface py-5 lg:py-4" aria-labelledby="gallery-heading" style="order: {{ $homepageSections['gallery']->sort_order }}">
        <div class="wm-container wm-photo-band grid gap-4 lg:gap-3">
            <div class="flex flex-col justify-center pb-2 lg:pb-0">
                <p class="text-xs font-semibold uppercase tracking-[0.15em] text-brand">Our community</p>
                <h2 id="gallery-heading" class="mt-2 text-3xl lg:mt-1 lg:text-2xl">{{ $homepageSections['gallery']->heading ?: 'Photos from our walks & holidays' }}</h2>
                <p class="mt-2 text-xs text-ink-muted lg:mt-1">{{ $homepageSections['gallery']->supporting_copy ?: 'Moments worth sharing.' }}</p>
                <a class="mt-3 text-sm font-semibold text-brand lg:mt-2 lg:text-xs" href="{{ $homepageSections['gallery']->cta_url ?: route('gallery.index') }}">{{ $homepageSections['gallery']->cta_label ?: 'View gallery' }} <span aria-hidden="true">&rarr;</span></a>
            </div>

            @forelse ($homepage->gallery as $index => $photo)
                <figure data-homepage-memory class="{{ $index === 0 ? 'wm-photo-feature' : '' }} h-36 self-center overflow-hidden rounded-[var(--wm-radius-md)] bg-surface-soft lg:h-28">
                    <a class="block size-full" href="{{ $photo['detail_url'] ?? route('gallery.index') }}"><img class="size-full object-cover transition-transform duration-300 hover:scale-[1.025]" src="{{ $photo['image_url'] }}" alt="{{ $photo['image_alt'] }}" loading="lazy" width="{{ $photo['width'] ?? '' }}" height="{{ $photo['height'] ?? '' }}" style="{{ $photo['rotation_style'] ?? '' }}"></a>
                </figure>
            @empty
                <p class="self-center text-sm text-ink-muted">No recent photos yet.</p>
            @endforelse

            <div class="wm-photo-upload flex flex-col gap-2 text-xs text-ink-muted sm:flex-row sm:items-center sm:justify-between">
                <p>Members can upload photos linked to specific walks and holidays.</p>
                <a class="shrink-0 font-semibold text-brand" href="{{ route('community-photos.upload.create') }}">Upload your photos <span aria-hidden="true">&rarr;</span></a>
            </div>
        </div>
    </section>
    @endif

    @if ($homepageSections->has('join'))
    <section data-homepage-section="join" data-layout="{{ $homepageSections['join']->layout_variant }}" class="wm-home-layout-{{ $homepageSections['join']->layout_variant }} bg-surface-raised py-6 sm:py-7 lg:py-3" style="order: {{ $homepageSections['join']->sort_order }}">
        <div class="wm-container grid gap-5 lg:grid-cols-[minmax(0,3fr)_minmax(18rem,1fr)] lg:gap-4">
            <div class="rounded-[var(--wm-radius-md)] bg-surface-soft p-4 sm:p-5 lg:p-3">
                <div class="flex flex-col gap-5 md:flex-row md:flex-wrap md:items-center lg:gap-3">
                    <div class="md:flex-[1_1_10rem]">
                        <p class="text-xs font-semibold uppercase tracking-[0.15em] text-brand">New here?</p>
                        <h2 class="mt-2 text-3xl lg:mt-1 lg:text-2xl">{{ $homepageSections['join']->heading ?: ($joinPage?->title ?: 'Join us!') }}</h2>
                        <p class="mt-3 text-sm text-ink-muted lg:mt-2 lg:text-xs">{{ $homepageSections['join']->supporting_copy ?: 'A friendly, volunteer-led group for people who enjoy the outdoors.' }}</p>
                        @if (! $configuredSectionKeys->contains('testimonial') && filled($homepage->testimonial))<blockquote class="mt-3 text-xs italic text-ink-muted lg:mt-2">{{ $homepage->testimonial }}</blockquote>@endif
                    </div>

                    <div class="grid gap-4 sm:grid-cols-3 md:flex-[2_1_20rem] lg:gap-3">
                        <p class="border-l-2 border-brand/25 pl-4 text-sm lg:pl-3 lg:text-xs"><strong class="block">Everyone welcome</strong><span class="text-ink-muted">Come as you are.</span></p>
                        <p class="border-l-2 border-brand/25 pl-4 text-sm lg:pl-3 lg:text-xs"><strong class="block">Try a walk</strong><span class="text-ink-muted">Find your pace.</span></p>
                        <p class="border-l-2 border-brand/25 pl-4 text-sm lg:pl-3 lg:text-xs"><strong class="block">Good company</strong><span class="text-ink-muted">Share the day.</span></p>
                    </div>

                    <x-public.button class="md:flex-[0_0_auto]" :href="$homepageSections['join']->cta_url ?: ($joinPage ? route('cms.show', $joinPage->slug) : route('new-here'))">{{ $homepageSections['join']->cta_label ?: 'Join the group' }}</x-public.button>
                </div>
            </div>

            <aside class="rounded-[var(--wm-radius-md)] border border-border bg-surface p-4 lg:p-3" aria-labelledby="resources-heading">
                <h2 id="resources-heading" class="text-xl lg:text-lg">Member resources</h2>
                <ul class="mt-2 divide-y divide-border text-xs">
                    @foreach ($homepage->memberResources as $resource)
                        <li><a class="flex min-h-8 items-center justify-between gap-3 py-1.5 hover:text-brand lg:min-h-7 lg:py-1" href="{{ $resource['url'] }}"><span>{{ $resource['label'] }}</span><span aria-hidden="true">&rarr;</span></a></li>
                    @endforeach
                </ul>
            </aside>
        </div>
    </section>
    @endif

    @if ($homepageSections->has('holiday') && ($homepage->holiday !== [] || $homepageSections['holiday']->empty_behavior === 'message'))
    <section data-homepage-section="holiday" data-layout="{{ $homepageSections['holiday']->layout_variant }}" class="wm-home-layout-{{ $homepageSections['holiday']->layout_variant }} bg-surface-raised py-7" style="order: {{ $homepageSections['holiday']->sort_order }}">
        <div class="wm-container"><h2 class="text-3xl">{{ $homepageSections['holiday']->heading ?: 'Holidays & Weekends Away' }}</h2>
        @if(filled($homepageSections['holiday']->supporting_copy))<p class="mt-2 text-sm text-ink-muted">{{ $homepageSections['holiday']->supporting_copy }}</p>@endif
        @if($homepage->holiday === [])<p class="mt-5 text-sm text-ink-muted">No upcoming trips away.</p>@else
        <article class="mt-5 overflow-hidden rounded-[var(--wm-radius-md)] border border-border bg-surface shadow-[var(--wm-shadow-card)] md:grid md:grid-cols-[1.3fr_1fr]">
            <img class="h-full w-full object-cover" src="{{ $homepage->holiday['image_url'] }}" alt="{{ $homepage->holiday['image_alt'] }}" loading="lazy">
            <div class="p-5"><h3 class="text-xl"><a href="{{ $homepage->holiday['url'] }}">{{ $homepage->holiday['title'] }}</a></h3><p class="mt-2 text-sm text-ink-muted">{{ $homepage->holiday['date'] }} · {{ $homepage->holiday['location'] }}</p><p class="mt-2 text-sm">{{ $homepage->holiday['summary'] }}</p></div>
        </article>@endif
        <a class="mt-4 inline-flex text-sm font-semibold text-brand" href="{{ $homepageSections['holiday']->cta_url ?: route('holidays.index') }}">{{ $homepageSections['holiday']->cta_label ?: 'View all' }} <span aria-hidden="true">&rarr;</span></a></div>
    </section>
    @endif

    @if ($homepageSections->has('testimonial') && (filled($homepage->testimonial) || $homepageSections['testimonial']->empty_behavior === 'message'))
    <section data-homepage-section="testimonial" data-layout="{{ $homepageSections['testimonial']->layout_variant }}" class="wm-home-layout-{{ $homepageSections['testimonial']->layout_variant }} bg-surface-soft py-7" style="order: {{ $homepageSections['testimonial']->sort_order }}">
        <div class="wm-container"><h2 class="text-2xl">{{ $homepageSections['testimonial']->heading ?: 'From our members' }}</h2>
        @if(filled($homepageSections['testimonial']->supporting_copy))<p class="mt-2 text-sm text-ink-muted">{{ $homepageSections['testimonial']->supporting_copy }}</p>@endif
        @if(filled($homepage->testimonial))<blockquote class="mt-4 text-lg italic">{{ $homepage->testimonial }}</blockquote>@else<p class="mt-4 text-sm text-ink-muted">No member story is currently featured.</p>@endif
        @if(filled($homepageSections['testimonial']->cta_label))<a class="mt-4 inline-flex text-sm font-semibold text-brand" href="{{ $homepageSections['testimonial']->cta_url ?: route('new-here') }}">{{ $homepageSections['testimonial']->cta_label }} <span aria-hidden="true">&rarr;</span></a>@endif</div>
    </section>
    @endif

    @if ($homepageSections->has('news') && ($homeNews !== [] || $homepageSections['news']->empty_behavior === 'message'))
    <section data-homepage-section="news" data-layout="{{ $homepageSections['news']->layout_variant }}" class="wm-home-layout-{{ $homepageSections['news']->layout_variant }} border-y border-border bg-surface py-8 sm:py-10" aria-labelledby="news-heading" style="order: {{ $homepageSections['news']->sort_order }}">
        <div class="wm-container">
            <div class="flex flex-wrap items-end justify-between gap-4">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.15em] text-brand">From the group</p>
                    <h2 id="news-heading" class="mt-2 text-3xl text-ink">{{ $homepageSections['news']->heading ?: 'Latest news' }}</h2>
                    @if (filled($homepageSections['news']->supporting_copy))<p class="mt-2 max-w-2xl text-sm text-ink-muted">{{ $homepageSections['news']->supporting_copy }}</p>@endif
                </div>
                <a class="text-sm font-semibold text-brand" href="{{ $homepageSections['news']->cta_url ?: route('news.index') }}">{{ $homepageSections['news']->cta_label ?: 'All news' }} <span aria-hidden="true">&rarr;</span></a>
            </div>
            @if ($homeNews === [])
                <p class="mt-6 text-sm text-ink-muted">No current news.</p>
            @else
                <div class="mt-6 grid gap-4 md:grid-cols-3">
                    @foreach ($homeNews as $article)
                        <article class="overflow-hidden rounded-[var(--wm-radius-md)] border border-border bg-surface-raised shadow-[var(--wm-shadow-card)] {{ $homepageSections['news']->layout_variant === 'featured' && $loop->first ? 'md:col-span-2' : '' }}">
                            @if ($article['image'])<img class="aspect-[16/7] w-full object-cover" src="{{ $article['image']->url }}" alt="{{ $article['image']->alt }}" width="{{ $article['image']->width }}" height="{{ $article['image']->height }}" loading="lazy">@endif
                            <div class="p-5"><p class="text-xs font-semibold uppercase tracking-wider text-brand">{{ $article['category'] }}</p><h3 class="mt-2 text-xl"><a class="hover:text-brand" href="{{ $article['url'] }}">{{ $article['title'] }}</a></h3>@if(filled($article['summary']))<p class="mt-2 text-sm text-ink-muted">{{ $article['summary'] }}</p>@endif</div>
                        </article>
                    @endforeach
                </div>
            @endif
        </div>
    </section>
    @endif
    </div>
@endsection

@section('site-footer')
    <x-public.site-footer :site="$homepage->site" />
@endsection
