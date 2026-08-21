@extends('layouts.account')

@section('title', 'Create your account')

@section('content')
    <section class="bg-surface-raised py-10 sm:py-14">
        <div class="wm-container max-w-[34rem]">
            <p class="text-xs font-semibold uppercase tracking-[0.15em] text-brand">Join the community</p>
            <h1 class="mt-2 text-4xl text-ink sm:text-5xl">Create your account</h1>
            <p class="mt-4 text-ink-muted">Save your details for later and get ready to join in.</p>

            <form class="mt-8 rounded-[var(--wm-radius-md)] border border-border bg-surface p-5 shadow-[var(--wm-shadow-card)] sm:p-7" method="POST" action="{{ route('register.store') }}">
                @csrf

                <div class="grid gap-5">
                    <label class="grid gap-1.5 text-sm font-semibold text-ink" for="name">Your name
                        <input class="wm-form-control font-normal" id="name" name="name" type="text" value="{{ old('name') }}" autocomplete="name" required autofocus>
                        @error('name') <span class="text-sm font-normal text-[var(--wm-critical)]">{{ $message }}</span> @enderror
                    </label>
                    <label class="grid gap-1.5 text-sm font-semibold text-ink" for="email">Email address
                        <input class="wm-form-control font-normal" id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="email" required>
                        @error('email') <span class="text-sm font-normal text-[var(--wm-critical)]">{{ $message }}</span> @enderror
                    </label>
                    <label class="grid gap-1.5 text-sm font-semibold text-ink" for="password">Password
                        <input class="wm-form-control font-normal" id="password" name="password" type="password" autocomplete="new-password" required>
                        @error('password') <span class="text-sm font-normal text-[var(--wm-critical)]">{{ $message }}</span> @enderror
                    </label>
                    <label class="grid gap-1.5 text-sm font-semibold text-ink" for="password_confirmation">Confirm password
                        <input class="wm-form-control font-normal" id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required>
                    </label>
                </div>

                <x-public.button class="mt-6 w-full" type="submit">Create account</x-public.button>
            </form>

            <p class="mt-5 text-center text-sm text-ink-muted">Already have an account? <a class="font-semibold text-brand" href="{{ route('login') }}">Sign in</a></p>
        </div>
    </section>
@endsection
