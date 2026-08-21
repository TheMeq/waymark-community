@extends('layouts.account')

@section('title', 'Leader Hub')

@section('content')
    <section class="bg-surface-raised py-10 sm:py-14">
        <div class="wm-container max-w-[var(--wm-container-wide)]">
            <div class="flex flex-wrap items-end justify-between gap-5">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.15em] text-brand">Your walks</p>
                    <h1 class="mt-2 text-4xl text-ink sm:text-5xl">Leader Hub</h1>
                    <p class="mt-3 text-ink-muted">Manage walks you organise as {{ $leaderName }}.</p>
                </div>
                <div class="flex flex-wrap gap-3">
                    @if ($createWalkUrl)
                        <x-public.button :href="$createWalkUrl">Create walk</x-public.button>
                    @endif
                    <x-public.button :href="route('leader-hub.profile.edit')" variant="secondary">Leader profile</x-public.button>
                </div>
            </div>

            @foreach (['drafts' => 'Draft walks', 'upcoming' => 'Upcoming and current walks', 'past' => 'Past walks'] as $key => $heading)
                <section class="mt-10" aria-labelledby="{{ $key }}-heading">
                    <h2 id="{{ $key }}-heading" class="text-2xl text-ink">{{ $heading }}</h2>
                    @if ($walks[$key] === [])
                        <p class="mt-3 text-ink-muted">No walks in this group.</p>
                    @else
                        <div class="mt-4 grid gap-4">
                            @foreach ($walks[$key] as $walk)
                                <article class="rounded-[var(--wm-radius-md)] border border-border bg-surface p-5 shadow-[var(--wm-shadow-card)]">
                                    <div class="flex flex-wrap items-start justify-between gap-4">
                                        <div>
                                            <h3 class="text-lg text-ink">{{ $walk['title'] }}</h3>
                                            <p class="mt-1 text-sm text-ink-muted">{{ $walk['when'] }} · {{ $walk['status'] }}</p>
                                        </div>
                                        <div class="flex flex-wrap gap-3 text-sm font-semibold">
                                            @if ($walk['edit_url'])
                                                <a class="text-brand underline" href="{{ $walk['edit_url'] }}">Edit</a>
                                            @endif
                                            <a class="text-brand underline" href="{{ $walk['duplicate_url'] }}">Duplicate</a>
                                        </div>
                                    </div>
                                    @if (filled($walk['notes']))
                                        <section class="mt-4 rounded-[var(--wm-radius-sm)] border border-border bg-surface-soft p-4" aria-label="Private organiser notes">
                                            <p class="text-xs font-semibold uppercase tracking-[0.1em] text-ink-muted">Private organiser notes</p>
                                            <p class="mt-2 text-sm text-ink">{{ $walk['notes'] }}</p>
                                        </section>
                                    @endif
                                </article>
                            @endforeach
                        </div>
                    @endif
                </section>
            @endforeach
        </div>
    </section>
@endsection
