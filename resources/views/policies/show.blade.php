@extends('layouts.public')
@section('title', $policy->title)
@section('site-header')<x-public.site-header :site="$site" />@endsection
@section('content')<article class="wm-container py-12 sm:py-16"><div class="mx-auto max-w-4xl"><h1 class="wm-heading-xl">{{ $policy->title }}</h1><p class="mt-4 text-sm text-ink-muted">Version {{ $policy->currentVersion->version_number }} · {{ $policy->currentVersion->published_at->format('j F Y') }}</p><div class="wm-prose mt-10">{!! nl2br(e($policy->currentVersion->body)) !!}</div><p class="mt-10 rounded-[var(--wm-radius-card)] bg-surface-soft p-4 text-sm">{{ $policy->review_notice }}</p></div></article>@endsection
@section('site-footer')<x-public.site-footer :site="$site" />@endsection
