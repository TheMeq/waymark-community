@extends('layouts.public')

@section('title', 'Upcoming walks')
@section('meta_description', 'Browse upcoming walks and practical details.')

@section('site-header')
    <x-public.site-header :site="$site" />
@endsection

@section('content')
    <section class="bg-surface-raised py-10 sm:py-14">
        <div class="wm-container">
            <p class="text-xs font-semibold uppercase tracking-[0.15em] text-brand">Plan a walk</p>
            <h1 class="mt-2 text-4xl text-ink sm:text-5xl">Upcoming walks</h1>

            <form class="mt-8 grid gap-3 rounded-[var(--wm-radius-md)] border border-border bg-surface p-4 sm:grid-cols-2 lg:grid-cols-4" method="get" action="{{ route('walks.index') }}">
                <div><label class="text-sm font-medium" for="date-from">From date</label><input class="wm-form-control mt-1" id="date-from" type="date" name="date_from" value="{{ $filters->dateFrom?->format('Y-m-d') }}"></div>
                <div><label class="text-sm font-medium" for="date-to">To date</label><input class="wm-form-control mt-1" id="date-to" type="date" name="date_to" value="{{ $filters->dateTo?->format('Y-m-d') }}"></div>
                <div><label class="text-sm font-medium" for="min-distance">Minimum distance</label><input class="wm-form-control mt-1" id="min-distance" type="number" min="0" step="0.01" name="min_distance" value="{{ $filters->minimumDistance }}"></div>
                <div><label class="text-sm font-medium" for="max-distance">Maximum distance</label><input class="wm-form-control mt-1" id="max-distance" type="number" min="0" step="0.01" name="max_distance" value="{{ $filters->maximumDistance }}"></div>
                <div><label class="text-sm font-medium" for="min-ascent">Minimum ascent</label><input class="wm-form-control mt-1" id="min-ascent" type="number" min="0" step="0.01" name="min_ascent" value="{{ $filters->minimumAscent }}"></div>
                <div><label class="text-sm font-medium" for="max-ascent">Maximum ascent</label><input class="wm-form-control mt-1" id="max-ascent" type="number" min="0" step="0.01" name="max_ascent" value="{{ $filters->maximumAscent }}"></div>
                <div><label class="text-sm font-medium" for="location">Location</label><input class="wm-form-control mt-1" id="location" type="search" name="location" value="{{ $filters->location }}"></div>
                <div><label class="text-sm font-medium" for="time-grouping">Time</label><select class="wm-form-control mt-1" id="time-grouping" name="time_grouping"><option value="">Any time</option><option value="weekend" @selected($filters->timeGrouping === 'weekend')>Weekend</option><option value="evening" @selected($filters->timeGrouping === 'evening')>Evening</option></select></div>
                <div><label class="text-sm font-medium" for="grade">Difficulty</label><select class="wm-form-control mt-1" id="grade" name="grade"><option value="">Any difficulty</option>@foreach ($grades as $grade)<option value="{{ $grade->id }}" @selected($filters->gradeId === $grade->id)>{{ $grade->name }}</option>@endforeach</select></div>
                <div><label class="text-sm font-medium" for="leader">Leader</label><select class="wm-form-control mt-1" id="leader" name="leader"><option value="">Any leader</option>@foreach ($leaders as $leader)<option value="{{ $leader->id }}" @selected($filters->leaderId === $leader->id)>{{ $leader->publicDisplayName() }}</option>@endforeach</select></div>
                <fieldset><legend class="text-sm font-medium">Themes</legend><div class="mt-1 flex flex-wrap gap-2">@foreach ($tags as $tag)<label class="text-sm"><input type="checkbox" name="tags[]" value="{{ $tag->id }}" @checked(in_array($tag->id, $filters->tagIds, true))> {{ $tag->name }}</label>@endforeach</div></fieldset>
                <label class="flex min-h-11 items-end gap-2 text-sm font-medium"><input type="checkbox" name="public_transport" value="1" @checked($filters->publicTransport)> Public-transport friendly</label>
                <div class="flex items-end gap-3"><x-public.button type="submit">Apply filters</x-public.button><a class="pb-2 text-sm font-semibold text-brand underline" href="{{ route('walks.index') }}">Clear</a></div>
            </form>

            @if ($walks->isNotEmpty())
                <div class="mt-8 grid gap-5 md:grid-cols-2 xl:grid-cols-3">
                    @foreach ($walks as $walk)
                        <x-public.event-card :event="$walk" />
                    @endforeach
                </div>

                <div class="mt-8">
                    {{ $walks->links() }}
                </div>
            @else
                <p class="mt-6 max-w-xl text-ink-muted">There are no upcoming walks to show right now. Please check back soon.</p>
            @endif
        </div>
    </section>
@endsection

@section('site-footer')
    <x-public.site-footer :site="$site" />
@endsection
