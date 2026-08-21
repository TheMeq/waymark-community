@extends('layouts.account')

@section('title', 'Verify your email')

@section('content')
    <section class="bg-surface-raised py-10 sm:py-14">
        <div class="wm-container max-w-[34rem]">
            <p class="text-xs font-semibold uppercase tracking-[0.15em] text-brand">One last step</p>
            <h1 class="mt-2 text-4xl text-ink sm:text-5xl">Verify your email</h1>
            <p class="mt-4 text-ink-muted">Check your inbox for a verification link. You can still use your account while you wait; features that need a confirmed identity will ask you to verify first.</p>

            @if (session('status') === 'verification-link-sent')
                <p class="mt-6 rounded-[var(--wm-radius-sm)] border border-brand/30 bg-surface-soft p-4 text-sm text-ink" role="status">A fresh verification link has been sent.</p>
            @endif

            <div class="mt-8 rounded-[var(--wm-radius-md)] border border-border bg-surface p-5 shadow-[var(--wm-shadow-card)] sm:p-7">
                <form method="POST" action="{{ route('verification.send') }}">
                    @csrf
                    <x-public.button class="w-full" type="submit" variant="secondary">Resend verification email</x-public.button>
                </form>
                <form class="mt-4 text-center" method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button class="text-sm font-semibold text-brand underline underline-offset-4" type="submit">Sign out</button>
                </form>
            </div>
        </div>
    </section>
@endsection
