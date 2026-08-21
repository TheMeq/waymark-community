@extends('layouts.account')

@section('title', 'Account security')
@section('meta_description', 'Manage two-factor authentication for your Waymark Community account.')

@section('content')
    <section class="bg-surface-raised py-10 sm:py-14">
        <div class="wm-container max-w-[var(--wm-container-copy)]">
            <p class="text-xs font-semibold uppercase tracking-[0.15em] text-brand">Your account</p>
            <h1 class="mt-2 text-4xl text-ink sm:text-5xl">Account security</h1>
            <p class="mt-4 text-ink-muted">Add an extra check when you sign in.</p>

            @if (session('status'))
                <p class="mt-6 rounded-[var(--wm-radius-sm)] border border-brand/30 bg-surface-soft p-4 text-sm text-ink" role="status">
                    @if (session('status') === \Laravel\Fortify\Fortify::TWO_FACTOR_AUTHENTICATION_ENABLED)
                        Your two-factor setup is ready to confirm.
                    @elseif (session('status') === \Laravel\Fortify\Fortify::TWO_FACTOR_AUTHENTICATION_CONFIRMED)
                        Two-factor authentication is enabled.
                    @elseif (session('status') === \Laravel\Fortify\Fortify::RECOVERY_CODES_GENERATED)
                        Your recovery codes have been replaced.
                    @elseif (session('status') === \Laravel\Fortify\Fortify::TWO_FACTOR_AUTHENTICATION_DISABLED)
                        Two-factor authentication is disabled.
                    @endif
                </p>
            @endif

            <section class="mt-8 rounded-[var(--wm-radius-md)] border border-border bg-surface p-5 shadow-[var(--wm-shadow-card)] sm:p-7" aria-labelledby="two-factor-heading">
                <h2 id="two-factor-heading" class="text-2xl text-ink">Two-factor authentication</h2>

                @if (! $twoFactorEnabled && ! $twoFactorSetupInProgress)
                    <p class="mt-3 text-sm text-ink-muted">Not enabled.</p>
                    <form class="mt-6" method="POST" action="{{ route('two-factor.enable') }}">
                        @csrf
                        <x-public.button type="submit">Set up two-factor authentication</x-public.button>
                    </form>
                @elseif ($twoFactorSetupInProgress)
                    <p class="mt-3 text-sm text-ink-muted">Scan this code with your authenticator app, then enter the current six-digit code to finish setup.</p>
                    <div class="mt-6 grid max-w-56 place-items-center overflow-hidden rounded-[var(--wm-radius-sm)] border border-border bg-white p-3" role="img" aria-label="Authenticator app setup QR code">
                        {!! $twoFactorQrCodeSvg !!}
                    </div>
                    <form class="mt-6 max-w-sm" method="POST" action="{{ route('two-factor.confirm') }}">
                        @csrf
                        <label class="grid gap-1.5 text-sm font-semibold text-ink" for="code">Authentication code
                            <input class="wm-form-control font-normal" id="code" name="code" type="text" inputmode="numeric" autocomplete="one-time-code" required autofocus aria-describedby="code-help @error('code', 'confirmTwoFactorAuthentication') code-error @enderror">
                            <span id="code-help" class="text-sm font-normal text-ink-muted">Use the code currently shown in your authenticator app.</span>
                            @error('code', 'confirmTwoFactorAuthentication') <span id="code-error" class="text-sm font-normal text-[var(--wm-critical)]">{{ $message }}</span> @enderror
                        </label>
                        <x-public.button class="mt-6" type="submit">Confirm two-factor authentication</x-public.button>
                    </form>
                @else
                    <p class="mt-3 text-sm text-ink-muted">Enabled.</p>
                    <section class="mt-6" aria-labelledby="recovery-codes-heading">
                        <h3 id="recovery-codes-heading" class="text-lg text-ink">Recovery codes</h3>
                        <p class="mt-2 text-sm text-ink-muted">Store these somewhere safe. Each code can be used once if you cannot use your authenticator app.</p>
                        <ul class="mt-4 grid gap-2 rounded-[var(--wm-radius-sm)] border border-border bg-surface-soft p-4 font-mono text-sm text-ink sm:grid-cols-2" aria-label="Your recovery codes">
                            @foreach ($recoveryCodes as $recoveryCode)
                                <li>{{ $recoveryCode }}</li>
                            @endforeach
                        </ul>
                        <form class="mt-5" method="POST" action="{{ route('two-factor.regenerate-recovery-codes') }}">
                            @csrf
                            <x-public.button type="submit" variant="secondary">Replace recovery codes</x-public.button>
                        </form>
                    </section>
                    <form class="mt-8 border-t border-border pt-6" method="POST" action="{{ route('two-factor.disable') }}">
                        @csrf
                        @method('DELETE')
                        <x-public.button type="submit" variant="quiet">Disable two-factor authentication</x-public.button>
                    </form>
                @endif
            </section>

            <p class="mt-6 text-sm"><a class="font-semibold text-brand" href="{{ route('account.profile.edit') }}">Back to profile settings</a></p>
        </div>
    </section>
@endsection
