@extends('layouts.public')

@section('title', "What's on")
@section('meta_description', 'Walks, socials and weekends away.')
@section('site-header')<x-public.site-header :site="$site" />@endsection

@section('content')
    <section class="bg-surface-raised py-10 sm:py-14">
        <div class="wm-container">
            <div class="flex flex-wrap items-end justify-between gap-4">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.15em] text-brand">Walk &middot; Explore &middot; Connect</p>
                    <h1 class="mt-2 text-4xl text-ink sm:text-5xl">{{ $month ? $month->format('F Y') : "What's on" }}</h1>
                </div>
                <a class="wm-button wm-button-secondary" href="{{ route('events.calendar', array_filter(['type' => $selectedType, 'month' => $month?->format('Y-m')])) }}">Calendar view</a>
            </div>

            <nav class="mt-6 flex flex-wrap gap-2" aria-label="Filter events by type">
                @foreach ([null => 'All events', 'walk' => 'Walks', 'social' => 'Socials', 'holiday' => 'Holidays'] as $value => $label)
                    <a class="inline-flex min-h-11 items-center rounded-[var(--wm-radius-pill)] border px-5 py-2 text-sm font-semibold {{ $selectedType === $value ? 'border-brand bg-brand text-on-brand' : 'border-border bg-surface-raised text-ink' }}" href="{{ route('events.index', array_filter(['type' => $value, 'month' => $month?->format('Y-m')])) }}">{{ $label }}</a>
                @endforeach
            </nav>

            @if ($events->isNotEmpty())
                <div class="mt-8 grid gap-5 md:grid-cols-2 xl:grid-cols-3">
                    @foreach ($events as $event)<x-public.event-card :event="$event" />@endforeach
                </div>
                <div class="mt-8">{{ $events->links() }}</div>
            @else
                <p class="mt-8 text-ink-muted">There are no events to show for this selection.</p>
            @endif
        </div>
    </section>
@endsection

@section('site-footer')<x-public.site-footer :site="$site" />@endsection
