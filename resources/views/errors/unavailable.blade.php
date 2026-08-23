@extends('layouts.public')
@section('title', 'Content unavailable')
@section('site-header')<x-public.site-header :site="$site" />@endsection
@section('content')
<section class="wm-container py-16 sm:py-24"><div class="mx-auto max-w-3xl text-center"><p class="wm-eyebrow">No longer available</p><h1 class="wm-heading-xl mt-2">{{ $content->title }} is unavailable</h1><p class="mt-4 text-ink-muted">It may have moved, been updated or no longer be public.</p><form class="mx-auto mt-8 flex max-w-xl gap-3" action="{{ route('search') }}" method="get" role="search"><label class="sr-only" for="unavailable-search">Search Waymark</label><input class="wm-form-control" id="unavailable-search" name="q" placeholder="Search Waymark"><x-public.button type="submit">Search Waymark</x-public.button></form></div></section>
@endsection
@section('site-footer')<x-public.site-footer :site="$site" />@endsection
