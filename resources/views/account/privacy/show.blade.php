@extends('layouts.account')

@section('title', 'Privacy and account lifecycle')

@section('content')
    <section class="bg-surface-raised py-10 sm:py-14">
        <div class="wm-container max-w-[var(--wm-container-copy)]">
            <p class="text-xs font-semibold uppercase tracking-[0.15em] text-brand">Your account</p>
            <h1 class="mt-2 text-4xl text-ink sm:text-5xl">Privacy and account lifecycle</h1>
            @if (session('status')) <p class="mt-6 rounded-[var(--wm-radius-sm)] border border-brand/30 bg-surface-soft p-4 text-sm text-ink" role="status">{{ session('status') }}</p> @endif
            <div class="mt-8 grid gap-6">
                <section class="rounded-[var(--wm-radius-md)] border border-border bg-surface p-5 shadow-[var(--wm-shadow-card)] sm:p-7" aria-labelledby="export-heading">
                    <h2 id="export-heading" class="text-2xl text-ink">Download your data</h2>
                    <p class="mt-3 text-sm text-ink-muted">Create a private copy of your account details, preferences and saved events. Downloads expire after seven days.</p>
                    <form class="mt-5" method="POST" action="{{ route('account.privacy.exports.request') }}">@csrf <x-public.button type="submit">Request data export</x-public.button></form>
                    @if ($exports->isNotEmpty()) <ul class="mt-6 grid gap-3" aria-label="Recent data exports">
                        @foreach ($exports as $export)
                            <li class="flex flex-wrap items-center justify-between gap-3 rounded-[var(--wm-radius-sm)] border border-border p-4 text-sm">
                                <span class="capitalize text-ink">{{ $export->status }}</span>
                                @if ($export->status === 'ready' && ! $export->isExpired()) <a class="font-semibold text-brand" href="{{ $export->downloadUrl() }}">Download export</a> @endif
                            </li>
                        @endforeach
                    </ul> @endif
                </section>
                <section class="rounded-[var(--wm-radius-md)] border border-border bg-surface p-5 shadow-[var(--wm-shadow-card)] sm:p-7" aria-labelledby="deletion-heading">
                    <h2 id="deletion-heading" class="text-2xl text-ink">Request account deletion</h2>
                    <p class="mt-3 text-sm text-ink-muted">Deletion requests are reviewed by an administrator. Event history is retained with an anonymised account record.</p>
                    @if ($deletionRequest?->status === 'requested')
                        <p class="mt-5 text-sm font-semibold text-ink" role="status">Your request is awaiting review.</p>
                    @else
                        <form class="mt-5" method="POST" action="{{ route('account.privacy.deletion.request') }}">@csrf <x-public.button type="submit" variant="secondary">Request deletion</x-public.button></form>
                    @endif
                </section>
                <a class="text-sm font-semibold text-brand" href="{{ route('account.profile.edit') }}">Back to profile settings</a>
            </div>
        </div>
    </section>
@endsection
