@extends('layouts.public')
@section('title', 'Search')
@section('site-header')<x-public.site-header :site="$site" />@endsection
@section('content')
<section class="wm-container py-12 sm:py-16">
    <div class="max-w-3xl">
        <p class="wm-eyebrow">Explore Waymark</p>
        <h1 class="wm-heading-xl mt-2">Search</h1>
        <form class="mt-8 grid gap-4 rounded-[var(--wm-radius-card)] border border-border bg-surface-raised p-5 shadow-[var(--wm-shadow-card)] sm:grid-cols-2" method="get" action="{{ route('search') }}" role="search">
            <div class="sm:col-span-2"><label class="text-sm font-semibold" for="search-query">Search words</label><input class="wm-form-control mt-1" id="search-query" name="q" value="{{ $filters->term }}"></div>
            <div><label class="text-sm font-semibold" for="search-type">Type</label><select class="wm-form-control mt-1" id="search-type" name="type"><option value="">Everything</option>@foreach(['event'=>'Events','page'=>'Pages','news'=>'News','document'=>'Documents','album'=>'Gallery albums'] as $value=>$label)<option value="{{ $value }}" @selected($filters->type===$value)>{{ $label }}</option>@endforeach</select></div>
            <div><label class="text-sm font-semibold" for="search-location">Location</label><input class="wm-form-control mt-1" id="search-location" name="location" value="{{ $filters->location }}"></div>
            <div><label class="text-sm font-semibold" for="search-date-from">From date</label><input class="wm-form-control mt-1" id="search-date-from" type="date" name="date_from" value="{{ $filters->dateFrom?->toDateString() }}"></div>
            <div><label class="text-sm font-semibold" for="search-date-to">To date</label><input class="wm-form-control mt-1" id="search-date-to" type="date" name="date_to" value="{{ $filters->dateTo?->toDateString() }}"></div>
            <div><label class="text-sm font-semibold" for="search-difficulty">Difficulty</label><input class="wm-form-control mt-1" id="search-difficulty" name="difficulty" value="{{ $filters->difficulty }}"></div>
            <div><label class="text-sm font-semibold" for="search-category">Document category</label><input class="wm-form-control mt-1" id="search-category" name="document_category" value="{{ $filters->documentCategory }}"></div>
            <div class="sm:col-span-2"><x-public.button type="submit">Search</x-public.button></div>
        </form>
    </div>

    @if($filters->hasCriteria())
        <p class="mt-10 text-sm text-ink-muted" role="status">{{ $results->total() }} {{ Str::plural('result', $results->total()) }}</p>
        <div class="mt-4 grid max-w-4xl gap-4">
            @forelse($results as $result)
                <article class="rounded-[var(--wm-radius-card)] border border-border bg-surface-raised p-5 shadow-[var(--wm-shadow-card)]">
                    <p class="text-xs font-semibold uppercase tracking-wider text-brand">{{ $result->label }}@if($result->date) · <time datetime="{{ $result->date }}">{{ \Carbon\CarbonImmutable::parse($result->date)->format('j M Y') }}</time>@endif</p>
                    <h2 class="mt-1 text-2xl"><a class="wm-link" href="{{ $result->url }}">{{ $result->title }}</a></h2>
                    @if($result->summary)<p class="mt-2 text-sm text-ink-muted">{{ $result->summary }}</p>@endif
                </article>
            @empty
                <p class="rounded-[var(--wm-radius-card)] bg-surface p-5">No public results match those filters.</p>
            @endforelse
        </div>
        <div class="mt-8 max-w-4xl">{{ $results->links() }}</div>
    @endif
</section>
@endsection
@section('site-footer')<x-public.site-footer :site="$site" />@endsection
