@extends('layouts.account')

@section('title', 'New here?')
@section('meta_description', 'A short guide to joining in with your local walking community.')

@section('content')
    <section class="bg-surface-raised py-10 sm:py-14">
        <div class="wm-container max-w-[var(--wm-container-copy)]">
            <p class="text-xs font-semibold uppercase tracking-[0.15em] text-brand">New here?</p>
            <h1 class="mt-2 text-4xl text-ink sm:text-5xl">Find your next walk</h1>
            <p class="mt-5 max-w-2xl text-lg text-ink-muted">Choose a walk that feels right for you, meet the group, and take things at your own pace.</p>

            <div class="mt-8 grid gap-4 sm:grid-cols-3">
                <article class="rounded-[var(--wm-radius-md)] border border-border bg-surface p-5">
                    <h2 class="text-xl text-ink">Choose a walk</h2>
                    <p class="mt-3 text-sm text-ink-muted">Read the distance, terrain, pace and meeting details before deciding.</p>
                </article>
                <article class="rounded-[var(--wm-radius-md)] border border-border bg-surface p-5">
                    <h2 class="text-xl text-ink">Try a walk</h2>
                    <p class="mt-3 text-sm text-ink-muted">Newcomers can try up to three walks before membership is needed. We do not count or record your walks.</p>
                </article>
                <article class="rounded-[var(--wm-radius-md)] border border-border bg-surface p-5">
                    <h2 class="text-xl text-ink">Share photos thoughtfully</h2>
                    <p class="mt-3 text-sm text-ink-muted">Photos need a verified email address and are shared against a specific walk or event.</p>
                </article>
            </div>

            @auth
                @if (! auth()->user()->hasVerifiedEmail())
                    <aside class="mt-6 rounded-[var(--wm-radius-md)] border border-brand/30 bg-surface-soft p-5 sm:flex sm:items-center sm:justify-between sm:gap-6" aria-labelledby="verify-email-heading">
                        <div>
                            <h2 id="verify-email-heading" class="text-2xl text-ink">Verify your email</h2>
                            <p class="mt-2 text-sm text-ink-muted">Open the verification link we sent, or request a fresh one to unlock identity-checked features.</p>
                        </div>
                        <x-public.button class="mt-5 shrink-0 sm:mt-0" :href="route('verification.notice')" variant="secondary">Verify your email</x-public.button>
                    </aside>
                @endif
            @endauth

            <div class="mt-8 rounded-[var(--wm-radius-md)] bg-surface-soft p-5 sm:flex sm:items-center sm:justify-between sm:gap-6">
                <div>
                    <h2 class="text-2xl text-ink">Membership</h2>
                    <p class="mt-2 text-sm text-ink-muted">Your account helps you take part online. Any external membership is managed separately by the group.</p>
                </div>
                <div class="mt-5 flex shrink-0 flex-wrap gap-3 sm:mt-0">
                    <x-public.button href="{{ route('walks.index') }}">Browse walks</x-public.button>
                    @guest
                        <x-public.button href="{{ route('register') }}" variant="secondary">Create account</x-public.button>
                    @endguest
                </div>
            </div>
        </div>
    </section>
@endsection
