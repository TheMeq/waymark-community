@extends('layouts.public')

@section('title', 'Public component story')

@section('site-header')
    <x-public.site-header :site="$site" />
    <x-public.site-banner :banner="$banner" />
@endsection

@section('content')
    <div class="wm-container py-12">
        <x-public.section-heading eyebrow="Visual foundation" title="Public component story" />

        <section class="mt-10" aria-labelledby="actions-heading">
            <h2 id="actions-heading" class="text-2xl">Actions and labels</h2>
            <div class="mt-5 flex flex-wrap gap-3">
                <x-public.button :href="route('new-here')">Join us</x-public.button>
                <x-public.button :href="route('walks.index')" variant="secondary">Upcoming walks</x-public.button>
                <x-public.badge>Moderate</x-public.badge>
                <x-public.badge tone="accent">New</x-public.badge>
            </div>
        </section>

        <section class="mt-12" aria-labelledby="cards-heading">
            <h2 id="cards-heading" class="sr-only">Cards</h2>
            <div class="grid gap-6 sm:grid-cols-2">
                <x-public.event-card :event="$event" />
                <x-public.photo-card :photo="$photo" />
            </div>
        </section>
    </div>
@endsection

@section('site-footer')
    <x-public.site-footer :site="$site" />
@endsection
