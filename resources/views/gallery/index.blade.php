@extends('layouts.public')

@section('title', $title.' | '.$site['name'])
@section('site-header') @include('components.public.site-header', ['site' => $site]) @endsection
@section('site-footer') @include('components.public.site-footer', ['site' => $site]) @endsection

@section('content')
    <section class="wm-container py-[var(--wm-space-8)]">
        <p class="text-sm font-semibold uppercase tracking-[0.16em] text-brand">Community photos</p>
        <h1 class="mt-2 text-4xl md:text-6xl">{{ $title }}</h1>
        @if ($context)
            <p class="mt-4 text-ink-muted"><a class="underline" href="{{ $context['url'] }}">{{ $context['label'] }}</a></p>
        @endif

        @if ($contexts->isNotEmpty())
            <section class="mt-[var(--wm-space-7)]" aria-labelledby="gallery-contexts">
                <h2 id="gallery-contexts" class="text-2xl">Explore albums</h2>
                <div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($contexts as $item)
                        <a class="wm-gallery-card block no-underline" href="{{ $item['url'] }}"><img src="{{ $item['cover']->imageUrl }}" alt="" loading="lazy" width="{{ $item['cover']->width }}" height="{{ $item['cover']->height }}" class="aspect-[16/9] w-full object-cover"><span class="block p-4 font-semibold">{{ $item['label'] }} <span class="font-normal text-ink-muted">{{ $item['count'] }} approved {{ \Illuminate\Support\Str::plural('photo', $item['count']) }}</span></span></a>
                    @endforeach
                </div>
            </section>
        @endif

        @if ($photos->isEmpty())
            <p class="mt-[var(--wm-space-7)] text-ink-muted">No photos yet.</p>
            @if ($photos->hasMorePages())
                <x-public.button class="mt-[var(--wm-space-6)]" :href="$photos->nextPageUrl()" variant="secondary" data-load-more>Load more</x-public.button>
            @endif
        @else
            <div class="wm-gallery-grid mt-[var(--wm-space-7)]" x-data="galleryLightbox()" x-init="initialise()">
                @foreach ($photos as $item)
                    @php($photo = $item)
                    <article class="wm-gallery-card">
                            <a href="{{ $photo->detailUrl }}" data-gallery-photo data-detail-url="{{ $photo->detailUrl }}" @click.prevent="open($el)" class="block">
                                <img src="{{ $photo->imageUrl }}" alt="{{ $photo->caption ?? 'Community photo' }}" loading="lazy" width="{{ $photo->width }}" height="{{ $photo->height }}" style="{{ $photo->rotationStyle }}" class="aspect-[4/3] w-full object-cover">
                            </a>
                            @if ($photo->caption)<p class="px-4 pt-3 text-sm font-medium">{{ $photo->caption }}</p>@endif
                            @if ($photo->photographerName)<p class="px-4 pb-4 text-sm text-ink-muted">Photo: {{ $photo->photographerName }}</p>@endif
                        </article>
                @endforeach
                <dialog x-ref="dialog" class="wm-gallery-dialog" aria-label="Photo viewer" aria-describedby="gallery-lightbox-description" @close="returnFocus()" @keydown.escape.prevent="close()" @keydown.left.prevent="previous()" @keydown.right.prevent="next()" @keydown.tab.prevent="trap($event)">
                    <div class="wm-gallery-dialog-content" @touchstart="touchStart($event)" @touchend="touchEnd($event)">
                        <p id="gallery-lightbox-description" class="sr-only">Use previous and next controls, arrow keys, or swipe to browse photos. Press Escape to close.</p>
                        <button class="wm-gallery-dialog-close" type="button" @click="close()" aria-label="Close photo">×</button>
                        <div x-html="content"></div>
                        <div class="mt-4 flex justify-between gap-3"><x-public.button variant="secondary" @click="previous()" ::disabled="index === 0">Previous</x-public.button><x-public.button variant="secondary" @click="next()" ::disabled="index === links.length - 1">Next</x-public.button></div>
                    </div>
                </dialog>
            </div>
            @if ($photos->hasMorePages())
                <x-public.button class="mt-[var(--wm-space-6)]" :href="$photos->nextPageUrl()" variant="secondary" data-load-more>Load more</x-public.button>
            @endif
        @endif
    </section>
@endsection
