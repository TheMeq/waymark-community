@extends('layouts.public')

@section('title', $leader['name'])

@section('site-header')
    <x-public.site-header :site="$site" />
@endsection

@section('content')
    <section class="bg-surface-raised py-10 sm:py-14">
        <div class="wm-container">
            <div class="max-w-[var(--wm-container-copy)]">
                <p class="text-xs font-semibold uppercase tracking-[0.15em] text-brand">Walk leader</p>
                <div class="mt-3 flex flex-col gap-5 rounded-[var(--wm-radius-md)] border border-border bg-surface p-5 shadow-[var(--wm-shadow-card)] sm:flex-row sm:items-center sm:p-7">
                    @if (isset($leader['photo_url']))
                        <img data-leader-profile-photo class="size-24 shrink-0 rounded-full object-cover" src="{{ $leader['photo_url'] }}" alt="{{ $leader['name'] }}">
                    @endif
                    <div>
                        <h1 class="text-4xl text-ink sm:text-5xl">{{ $leader['name'] }}</h1>
                        @if (isset($leader['introduction']))
                            <p class="mt-3 text-ink-muted">{{ $leader['introduction'] }}</p>
                        @endif
                    </div>
                </div>
            </div>

            <section class="mt-10" aria-labelledby="leader-walks-heading">
                <h2 id="leader-walks-heading" class="text-2xl text-ink">Upcoming walks</h2>
                @if ($walks !== [])
                    <div class="mt-5 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                        @foreach ($walks as $walk)
                            <x-public.event-card :event="$walk" />
                        @endforeach
                    </div>
                @else
                    <p class="mt-4 text-ink-muted">There are no upcoming walks listed at the moment.</p>
                @endif
            </section>
        </div>
    </section>
@endsection

@section('site-footer')
    <x-public.site-footer :site="$site" />
@endsection
