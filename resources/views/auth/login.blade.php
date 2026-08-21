@extends('layouts.account')

@section('title', 'Welcome back')

@section('content')
    <section class="bg-surface-raised py-10 sm:py-14">
        <div class="wm-container max-w-[34rem]">
            <p class="text-xs font-semibold uppercase tracking-[0.15em] text-brand">Your account</p>
            <h1 class="mt-2 text-4xl text-ink sm:text-5xl">Welcome back</h1>
            <p class="mt-4 text-ink-muted">Sign in to continue with your account.</p>

            @if (session('status'))
                <p class="mt-6 rounded-[var(--wm-radius-sm)] border border-brand/30 bg-surface-soft p-4 text-sm text-ink" role="status">{{ session('status') }}</p>
            @endif

            <form class="mt-8 rounded-[var(--wm-radius-md)] border border-border bg-surface p-5 shadow-[var(--wm-shadow-card)] sm:p-7" method="POST" action="{{ route('login.store') }}">
                @csrf

                <div class="grid gap-5">
                    <label class="grid gap-1.5 text-sm font-semibold text-ink" for="email">Email address
                        <input class="wm-form-control font-normal" id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="email" required autofocus>
                        @error('email') <span class="text-sm font-normal text-[var(--wm-critical)]">{{ $message }}</span> @enderror
                    </label>
                    <label class="grid gap-1.5 text-sm font-semibold text-ink" for="password">Password
                        <input class="wm-form-control font-normal" id="password" name="password" type="password" autocomplete="current-password" required>
                        @error('password') <span class="text-sm font-normal text-[var(--wm-critical)]">{{ $message }}</span> @enderror
                    </label>
                    <label class="flex items-center gap-2 text-sm text-ink" for="remember">
                        <input class="size-4 accent-brand" id="remember" name="remember" type="checkbox">
                        Remember me
                    </label>
                </div>

                <x-public.button class="mt-6 w-full" type="submit">Sign in</x-public.button>
            </form>

            <div class="mt-5 flex flex-wrap justify-center gap-x-5 gap-y-2 text-sm">
                <a class="font-semibold text-brand" href="{{ route('password.request') }}">Forgot your password?</a>
                <a class="font-semibold text-brand" href="{{ route('register') }}">Create an account</a>
            </div>
        </div>
    </section>
@endsection
