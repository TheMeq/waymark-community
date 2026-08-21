@extends('layouts.account')

@section('title', 'Confirm your authentication code')
@section('meta_description', 'Confirm your authenticator code before continuing with a sensitive action.')

@section('content')
    <section class="bg-surface-raised py-10 sm:py-14">
        <div class="wm-container max-w-[34rem]">
            <p class="text-xs font-semibold uppercase tracking-[0.15em] text-brand">Security check</p>
            <h1 class="mt-2 text-4xl text-ink sm:text-5xl">Enter your code</h1>
            <p class="mt-4 text-ink-muted">Use the current code from your authenticator app before continuing with this sensitive action.</p>

            <form class="mt-8 rounded-[var(--wm-radius-md)] border border-border bg-surface p-5 shadow-[var(--wm-shadow-card)] sm:p-7" method="POST" action="{{ route('account.sensitive-confirmation.store') }}">
                @csrf
                <label class="grid gap-1.5 text-sm font-semibold text-ink" for="code">Authentication code
                    <input class="wm-form-control font-normal" id="code" name="code" type="text" inputmode="numeric" autocomplete="one-time-code" required autofocus aria-describedby="code-help @error('code') code-error @enderror">
                    <span id="code-help" class="text-sm font-normal text-ink-muted">Enter the six-digit code from the authenticator app for {{ $email }}.</span>
                    @error('code') <span id="code-error" class="text-sm font-normal text-[var(--wm-critical)]">{{ $message }}</span> @enderror
                </label>
                <x-public.button class="mt-6 w-full" type="submit">Continue</x-public.button>
            </form>
        </div>
    </section>
@endsection
