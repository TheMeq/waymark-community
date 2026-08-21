@extends('layouts.public')

@section('title', 'Weekends away')
@section('meta_description', 'Browse upcoming walking holidays and weekends away.')

@section('site-header')<x-public.site-header :site="$site" />@endsection

@section('content')
    <section class="bg-surface-soft py-10 sm:py-14">
        <div class="wm-container">
            <p class="text-sm font-semibold uppercase tracking-[0.14em] text-brand">Explore together</p>
            <h1 class="mt-2 text-4xl text-ink sm:text-5xl">Weekends away</h1>
            @if ($holidays->isNotEmpty())
                <div class="mt-8 grid gap-5 md:grid-cols-2 xl:grid-cols-3">
                    @foreach ($holidays as $holiday)<x-public.event-card :event="$holiday" />@endforeach
                </div>
                <div class="mt-8">{{ $holidays->links() }}</div>
            @else
                <p class="mt-6 text-ink-muted">There are no upcoming weekends away to show right now.</p>
            @endif
        </div>
    </section>
@endsection

@section('site-footer')<x-public.site-footer :site="$site" />@endsection
