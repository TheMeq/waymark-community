@extends('layouts.public')

@section('title', $page->seo_title ?: $page->title)

@section('content')
    <div class="wm-container py-12 sm:py-16">
        @if ($notice)
            <p class="mb-6 inline-flex rounded-full border border-[var(--wm-color-border)] bg-[var(--wm-color-surface-muted)] px-4 py-2 text-sm font-semibold">{{ $notice }}</p>
        @endif
        <article class="mx-auto max-w-4xl">
            <h1 class="wm-heading-xl">{{ $page->title }}</h1>
            <x-content.blocks class="mt-10" :blocks="$blocks" />
        </article>
    </div>
@endsection
