@extends('layouts.account')

@section('title', 'Confirm your password')

@section('content')
    <section class="bg-surface-raised py-10 sm:py-14">
        <div class="wm-container max-w-[34rem]">
            <p class="text-xs font-semibold uppercase tracking-[0.15em] text-brand">Security check</p>
            <h1 class="mt-2 text-4xl text-ink sm:text-5xl">Confirm your password</h1>
            <p class="mt-4 text-ink-muted">Confirm your password before continuing with this sensitive action.</p>

            <form class="mt-8 rounded-[var(--wm-radius-md)] border border-border bg-surface p-5 shadow-[var(--wm-shadow-card)] sm:p-7" method="POST" action="{{ route('password.confirm.store') }}">
                @csrf
                <label class="grid gap-1.5 text-sm font-semibold text-ink" for="password">Password
                    <input class="wm-form-control font-normal" id="password" name="password" type="password" autocomplete="current-password" required autofocus>
                    @error('password') <span class="text-sm font-normal text-[var(--wm-critical)]">{{ $message }}</span> @enderror
                </label>
                <x-public.button class="mt-6 w-full" type="submit">Continue</x-public.button>
            </form>
        </div>
    </section>
@endsection
