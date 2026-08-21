@extends('layouts.account')

@section('title', 'Saved events')
@section('meta_description', 'Your saved walks, socials and weekends away.')

@section('content')
    <section class="bg-surface-raised py-10 sm:py-14">
        <div class="wm-container max-w-[var(--wm-container-copy)]">
            <p class="text-xs font-semibold uppercase tracking-[0.15em] text-brand">Your account</p>
            <h1 class="mt-2 text-4xl text-ink sm:text-5xl">Saved events</h1>
            <p class="mt-4 text-ink-muted">Walks, socials and weekends away you have saved.</p>

            @if (! $hasFavourites)
                <p class="mt-8 rounded-[var(--wm-radius-md)] border border-border bg-surface p-5 text-ink-muted">No saved events yet.</p>
            @else
                <div class="mt-8 grid gap-8">
                    @foreach ($favourites as $heading => $items)
                        @if ($items !== [])
                            <section aria-labelledby="{{ str($heading)->slug() }}-heading">
                                <h2 id="{{ str($heading)->slug() }}-heading" class="text-2xl text-ink">{{ $heading }}</h2>
                                <ul class="mt-4 grid gap-3">
                                    @foreach ($items as $item)
                                        <li class="flex flex-wrap items-center justify-between gap-4 rounded-[var(--wm-radius-md)] border border-border bg-surface p-4 shadow-[var(--wm-shadow-card)]">
                                            <div>
                                                <a class="font-semibold text-ink hover:text-brand" href="{{ $item['url'] }}">{{ $item['title'] }}</a>
                                                <p class="mt-1 text-sm text-ink-muted">{{ $item['when'] }}</p>
                                            </div>
                                            <form method="POST" action="{{ $item['remove_url'] }}">
                                                @csrf
                                                @method('DELETE')
                                                <x-public.button type="submit" variant="secondary">Remove</x-public.button>
                                            </form>
                                        </li>
                                    @endforeach
                                </ul>
                            </section>
                        @endif
                    @endforeach
                </div>
            @endif
        </div>
    </section>
@endsection
