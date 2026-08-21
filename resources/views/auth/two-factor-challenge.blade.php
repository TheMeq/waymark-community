@extends('layouts.account')

@section('title', 'Security check')

@section('content')
    <section class="bg-surface-raised py-10 sm:py-14">
        <div class="wm-container max-w-[34rem]">
            <p class="text-xs font-semibold uppercase tracking-[0.15em] text-brand">Security check</p>
            <h1 class="mt-2 text-4xl text-ink sm:text-5xl">Enter your code</h1>
            <p class="mt-4 text-ink-muted">Use the code from your authenticator app, or one of your recovery codes.</p>

            <form class="mt-8 rounded-[var(--wm-radius-md)] border border-border bg-surface p-5 shadow-[var(--wm-shadow-card)] sm:p-7" method="POST" action="{{ route('two-factor.login.store') }}">
                @csrf
                <label class="grid gap-1.5 text-sm font-semibold text-ink" for="code">Authentication code
                    <input class="wm-form-control font-normal" id="code" name="code" type="text" inputmode="numeric" autocomplete="one-time-code" autofocus>
                    @error('code') <span class="text-sm font-normal text-[var(--wm-critical)]">{{ $message }}</span> @enderror
                </label>
                <label class="mt-5 grid gap-1.5 text-sm font-semibold text-ink" for="recovery_code">Recovery code
                    <input class="wm-form-control font-normal" id="recovery_code" name="recovery_code" type="text" autocomplete="off">
                    @error('recovery_code') <span class="text-sm font-normal text-[var(--wm-critical)]">{{ $message }}</span> @enderror
                </label>
                <x-public.button class="mt-6 w-full" type="submit">Continue</x-public.button>
            </form>
        </div>
    </section>
@endsection
