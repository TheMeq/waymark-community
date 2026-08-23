@extends('layouts.public')
@section('title', 'News')
@section('site-header')<x-public.site-header :site="$site" />@endsection
@section('content')
<section class="wm-container py-12 sm:py-16"><h1 class="wm-heading-xl">News</h1><div class="mt-10 grid gap-5 md:grid-cols-2 lg:grid-cols-3">@forelse($articles as $article)<article class="rounded-[var(--wm-radius-card)] border border-border bg-surface-raised p-6 shadow-[var(--wm-shadow-card)]"><p class="text-xs font-semibold uppercase tracking-wider text-brand">{{ $article->primary_category }}</p><h2 class="mt-2 text-2xl"><a href="{{ route('news.show', $article->slug) }}">{{ $article->title }}</a></h2><p class="mt-3 text-sm text-ink-muted">{{ $article->summary }}</p></article>@empty<p>No current news.</p>@endforelse</div><div class="mt-8">{{ $articles->links() }}</div></section>
@endsection
@section('site-footer')<x-public.site-footer :site="$site" />@endsection
