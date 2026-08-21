@extends('layouts.public')

@section('title', $month->format('F Y').' calendar')
@section('meta_description', 'Month calendar of walks, socials and weekends away.')
@section('site-header')<x-public.site-header :site="$site" />@endsection

@section('content')
    <section class="bg-surface-raised py-10 sm:py-14">
        <div class="wm-container">
            <div class="flex flex-wrap items-end justify-between gap-4">
                <div><p class="text-xs font-semibold uppercase tracking-[0.15em] text-brand">What's on</p><h1 class="mt-2 text-4xl text-ink sm:text-5xl">{{ $month->format('F Y') }}</h1></div>
                <a class="wm-button wm-button-secondary" href="{{ route('events.index', array_filter(['type' => $selectedType, 'month' => $month->format('Y-m')])) }}" aria-label="View {{ $month->format('F') }} events as a list">List view</a>
            </div>

            <nav class="mt-6 flex flex-wrap items-center justify-between gap-3" aria-label="Calendar navigation">
                <a class="min-h-11 rounded-[var(--wm-radius-pill)] border border-border px-5 py-2.5 font-semibold" href="{{ route('events.calendar', array_filter(['type' => $selectedType, 'month' => $month->subMonth()->format('Y-m')])) }}">&larr; Previous month</a>
                <a class="min-h-11 rounded-[var(--wm-radius-pill)] border border-border px-5 py-2.5 font-semibold" href="{{ route('events.calendar', array_filter(['type' => $selectedType, 'month' => $month->addMonth()->format('Y-m')])) }}">Next month &rarr;</a>
            </nav>

            <div class="mt-6 overflow-x-auto rounded-[var(--wm-radius-md)] border border-border" tabindex="0" aria-label="Scrollable month calendar">
                <table class="w-full min-w-[48rem] border-collapse bg-surface-raised">
                    <caption class="sr-only">{{ $month->format('F Y') }} events calendar. Use the list view for a linear alternative.</caption>
                    <thead><tr>@foreach (['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'] as $day)<th class="border-b border-border bg-surface-soft p-3 text-left text-xs font-semibold uppercase tracking-[0.08em] text-ink-muted" scope="col">{{ $day }}</th>@endforeach</tr></thead>
                    <tbody>
                        @foreach ($weeks as $week)
                            <tr>
                                @foreach ($week as $day)
                                    <td class="h-32 w-[14.285%] border-b border-r border-border p-2 align-top {{ $day['in_month'] ? '' : 'bg-surface-soft text-ink-muted' }}">
                                        <time class="text-xs font-semibold" datetime="{{ $day['date']->format('Y-m-d') }}">{{ $day['date']->format('j') }}</time>
                                        @if ($day['events'] !== [])<ul class="mt-2 grid gap-2">@foreach ($day['events'] as $event)<li><a class="block rounded-[var(--wm-radius-sm)] bg-surface-soft p-2 text-xs font-semibold text-ink hover:text-brand" href="{{ $event['url'] }}"><span class="block text-[0.65rem] font-normal text-brand">{{ $event['label'] }}@if ($event['status']) &middot; {{ $event['status'] }}@endif</span>{{ $event['title'] }}</a></li>@endforeach</ul>@endif
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <p class="mt-4 text-sm text-ink-muted"><a class="font-semibold text-brand underline" href="{{ route('events.index', array_filter(['type' => $selectedType, 'month' => $month->format('Y-m')])) }}">View {{ $month->format('F') }} events as a list</a>.</p>
        </div>
    </section>
@endsection

@section('site-footer')<x-public.site-footer :site="$site" />@endsection
