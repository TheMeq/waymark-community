@extends('layouts.public')
@section('title', 'Documents')
@section('site-header')<x-public.site-header :site="$site" />@endsection
@section('content')<section class="wm-container py-12 sm:py-16"><h1 class="wm-heading-xl">Documents</h1><div class="mt-10 grid gap-4 md:grid-cols-2">@forelse($documents as $document)<article class="rounded-[var(--wm-radius-card)] border border-border bg-surface-raised p-6"><p class="text-xs font-semibold uppercase tracking-wider text-brand">{{ $document->category->name }}</p><h2 class="mt-2 text-2xl"><a href="{{ route('documents.show', $document->slug) }}">{{ $document->title }}</a></h2><p class="mt-3 text-sm text-ink-muted">{{ $document->description }}</p></article>@empty<p>No public documents.</p>@endforelse</div></section>@endsection
@section('site-footer')<x-public.site-footer :site="$site" />@endsection
