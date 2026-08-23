@extends('layouts.public')
@section('title', 'Cookie settings')
@section('site-header')<x-public.site-header :site="$site" />@endsection
@section('content')
<section class="wm-container py-12 sm:py-16" aria-labelledby="cookie-settings-heading">
    <div class="mx-auto max-w-3xl">
        <p class="wm-eyebrow">Privacy</p>
        <h1 id="cookie-settings-heading" class="wm-heading-xl mt-3">Cookie settings</h1>
        <p class="mt-5 text-ink-muted">Essential cookies keep the site secure and working. Optional analytics helps the group understand which public pages are useful.</p>

        @if(session('status'))<p class="mt-6 rounded-[var(--wm-radius-card)] bg-surface-soft p-4" role="status">{{ session('status') }}</p>@endif

        <form class="mt-8 grid gap-6" method="post" action="{{ route('cookie-settings.update') }}">
            @csrf
            <div class="rounded-[var(--wm-radius-card)] border border-border bg-surface-raised p-5">
                <h2 class="text-xl">Essential cookies</h2>
                <p class="mt-2 text-sm text-ink-muted">Always active. Used for security, sign-in, sessions and your cookie choice.</p>
            </div>
            <input type="hidden" name="analytics" value="0">
            <label class="flex min-h-11 items-start gap-3 rounded-[var(--wm-radius-card)] border border-border bg-surface-raised p-5">
                <input class="mt-1 size-5" type="checkbox" name="analytics" value="1" @checked($preferences['analytics'])>
                <span><strong class="block">Optional analytics</strong><span class="mt-1 block text-sm text-ink-muted">Loads the configured approved analytics provider only after you allow it.</span></span>
            </label>
            <button class="wm-button wm-button-primary w-fit" type="submit">Save cookie settings</button>
        </form>
    </div>
</section>
@endsection
@section('site-footer')<x-public.site-footer :site="$site" />@endsection
