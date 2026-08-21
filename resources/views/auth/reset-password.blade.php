@extends('layouts.account')

@section('title', 'Choose a new password')

@section('content')
    <section class="bg-surface-raised py-10 sm:py-14">
        <div class="wm-container max-w-[34rem]">
            <p class="text-xs font-semibold uppercase tracking-[0.15em] text-brand">Account help</p>
            <h1 class="mt-2 text-4xl text-ink sm:text-5xl">Choose a new password</h1>
            <p class="mt-4 text-ink-muted">Use a password you do not use elsewhere.</p>

            <form class="mt-8 rounded-[var(--wm-radius-md)] border border-border bg-surface p-5 shadow-[var(--wm-shadow-card)] sm:p-7" method="POST" action="{{ route('password.update') }}">
                @csrf
                <input name="token" type="hidden" value="{{ $request->route('token') }}">

                <div class="grid gap-5">
                    <label class="grid gap-1.5 text-sm font-semibold text-ink" for="email">Email address
                        <input class="wm-form-control font-normal" id="email" name="email" type="email" value="{{ old('email', $request->email) }}" autocomplete="email" required autofocus>
                        @error('email') <span class="text-sm font-normal text-[var(--wm-critical)]">{{ $message }}</span> @enderror
                    </label>
                    <label class="grid gap-1.5 text-sm font-semibold text-ink" for="password">New password
                        <input class="wm-form-control font-normal" id="password" name="password" type="password" autocomplete="new-password" required>
                        @error('password') <span class="text-sm font-normal text-[var(--wm-critical)]">{{ $message }}</span> @enderror
                    </label>
                    <label class="grid gap-1.5 text-sm font-semibold text-ink" for="password_confirmation">Confirm new password
                        <input class="wm-form-control font-normal" id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required>
                    </label>
                </div>

                <x-public.button class="mt-6 w-full" type="submit">Reset password</x-public.button>
            </form>
        </div>
    </section>
@endsection
