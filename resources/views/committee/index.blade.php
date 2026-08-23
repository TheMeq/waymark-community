@extends('layouts.public')
@section('title', 'Committee')
@section('site-header')<x-public.site-header :site="$site" />@endsection
@section('content')<section class="wm-container py-12 sm:py-16"><h1 class="wm-heading-xl">Committee</h1><div class="mt-10 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">@forelse($roles as $role)<article class="rounded-[var(--wm-radius-card)] border border-border bg-surface-raised p-6"><p class="text-xs font-semibold uppercase tracking-wider text-brand">{{ $role->title }}</p><h2 class="mt-2 text-2xl">{{ $role->displayName() }}</h2>@if($role->public_details)<p class="mt-3 text-sm text-ink-muted">{{ $role->public_details }}</p>@endif</article>@empty<p>Committee details will appear here.</p>@endforelse</div><a class="mt-10 inline-block text-brand underline" href="{{ route('committee.meetings') }}">Committee meetings</a></section>@endsection
@section('site-footer')<x-public.site-footer :site="$site" />@endsection
