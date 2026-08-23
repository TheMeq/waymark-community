@extends('layouts.public')
@section('title', 'Page not found')
@section('site-header')<x-public.site-header :site="$site" />@endsection
@section('content')
<section class="wm-container py-16 sm:py-24"><div class="mx-auto max-w-3xl text-center"><p class="wm-eyebrow">404</p><h1 class="wm-heading-xl mt-2">We could not find that page</h1><p class="mt-4 text-ink-muted">Try a search or choose somewhere useful.</p><form class="mx-auto mt-8 flex max-w-xl gap-3" action="{{ route('search') }}" method="get" role="search"><label class="sr-only" for="not-found-search">Search Waymark</label><input class="wm-form-control" id="not-found-search" name="q" placeholder="Search Waymark"><x-public.button type="submit">Search</x-public.button></form><nav class="mt-10 flex flex-wrap justify-center gap-4" aria-label="Helpful links"><a class="wm-link" href="{{ route('walks.index') }}">Upcoming walks</a><a class="wm-link" href="{{ route('events.calendar') }}">Calendar</a><a class="wm-link" href="{{ route('gallery.index') }}">Gallery</a><a class="wm-link" href="{{ route('contact.create') }}">Contact</a></nav></div></section>
@endsection
@section('site-footer')<x-public.site-footer :site="$site" />@endsection
