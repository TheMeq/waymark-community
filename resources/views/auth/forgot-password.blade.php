@extends('layouts.account')

@section('title', 'Reset your password')

@section('content')
    <section class="bg-surface-raised py-10 sm:py-14">
        <div class="wm-container max-w-[34rem]">
            <p class="text-xs font-semibold uppercase tracking-[0.15em] text-brand">Account help</p>
            <h1 class="mt-2 text-4xl text-ink sm:text-5xl">Reset your password</h1>
            <p class="mt-4 text-ink-muted">Enter your email address and we will send you a reset link.</p>

            @if (session('status'))
                <p class="mt-6 rounded-[var(--wm-radius-sm)] border border-brand/30 bg-surface-soft p-4 text-sm text-ink" role="status">{{ session('status') }}</p>
            @endif

            <form class="mt-8 rounded-[var(--wm-radius-md)] border border-border bg-surface p-5 shadow-[var(--wm-shadow-card)] sm:p-7" method="POST" action="{{ route('password.email') }}">
                @csrf
                <label class="grid gap-1.5 text-sm font-semibold text-ink" for="email">Email address
                    <input class="wm-form-control font-normal" id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="email" required autofocus>
                    @error('email') <span class="text-sm font-normal text-[var(--wm-critical)]">{{ $message }}</span> @enderror
                </label>
                <x-public.button class="mt-6 w-full" type="submit">Email reset link</x-public.button>
            </form>

            <p class="mt-5 text-center text-sm text-ink-muted"><a class="font-semibold text-brand" href="{{ route('login') }}">Back to sign in</a></p>
        </div>
    </section>
@endsection
