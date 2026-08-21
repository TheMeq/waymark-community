@extends('layouts.public')

@section('title', 'Upcoming socials')
@section('meta_description', 'Browse upcoming social events.')

@section('site-header')<x-public.site-header :site="$site" />@endsection

@section('content')
    <section class="bg-surface-raised py-10 sm:py-14">
        <div class="wm-container">
            <p class="text-xs font-semibold uppercase tracking-[0.15em] text-brand">Meet the community</p>
            <h1 class="mt-2 text-4xl text-ink sm:text-5xl">Upcoming socials</h1>
            @if ($socials->isNotEmpty())
                <div class="mt-8 grid gap-5 md:grid-cols-2 xl:grid-cols-3">@foreach ($socials as $social)<x-public.event-card :event="$social" />@endforeach</div>
                <div class="mt-8">{{ $socials->links() }}</div>
            @else
                <p class="mt-6 text-ink-muted">There are no upcoming socials to show right now.</p>
            @endif
        </div>
    </section>
@endsection

@section('site-footer')<x-public.site-footer :site="$site" />@endsection
