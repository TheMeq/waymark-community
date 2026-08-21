@extends('layouts.account')

@section('title', 'Profile settings')
@section('meta_description', 'Update your Waymark Community profile and communication preferences.')

@section('content')
    <section class="bg-surface-raised py-10 sm:py-14">
        <div class="wm-container max-w-[var(--wm-container-copy)]">
            <p class="text-xs font-semibold uppercase tracking-[0.15em] text-brand">Your account</p>
            <h1 class="mt-2 text-4xl text-ink sm:text-5xl">Profile settings</h1>
            <p class="mt-4 text-ink-muted">Your account name stays private. Your public name is used when your contribution is shown.</p>

            @if (session('status'))
                <p class="mt-6 rounded-[var(--wm-radius-sm)] border border-brand/30 bg-surface-soft p-4 text-sm text-ink" role="status">{{ session('status') }}</p>
            @endif

            <form class="mt-8 grid gap-8" method="POST" action="{{ route('account.profile.update') }}">
                @csrf
                @method('PATCH')

                <section class="rounded-[var(--wm-radius-md)] border border-border bg-surface p-5 shadow-[var(--wm-shadow-card)] sm:p-7" aria-labelledby="profile-details-heading">
                    <h2 id="profile-details-heading" class="text-2xl text-ink">Your details</h2>
                    <div class="mt-6 grid gap-5">
                        <label class="grid gap-1.5 text-sm font-semibold text-ink" for="name">Account name
                            <input class="wm-form-control font-normal" id="name" name="name" type="text" value="{{ old('name', $account['name']) }}" autocomplete="name" required autofocus aria-describedby="name-help @error('name') name-error @enderror">
                            <span id="name-help" class="text-sm font-normal text-ink-muted">Used for your account and not displayed publicly.</span>
                            @error('name') <span id="name-error" class="text-sm font-normal text-[var(--wm-critical)]">{{ $message }}</span> @enderror
                        </label>
                        <label class="grid gap-1.5 text-sm font-semibold text-ink" for="display_name">Public display name <span class="font-normal text-ink-muted">(optional)</span>
                            <input class="wm-form-control font-normal" id="display_name" name="display_name" type="text" value="{{ old('display_name', $account['display_name']) }}" autocomplete="nickname" aria-describedby="display-name-help @error('display_name') display-name-error @enderror">
                            <span id="display-name-help" class="text-sm font-normal text-ink-muted">If blank, we use {{ $account['public_display_name'] }}.</span>
                            @error('display_name') <span id="display-name-error" class="text-sm font-normal text-[var(--wm-critical)]">{{ $message }}</span> @enderror
                        </label>
                        <label class="grid gap-1.5 text-sm font-semibold text-ink" for="phone">Phone number <span class="font-normal text-ink-muted">(optional)</span>
                            <input class="wm-form-control font-normal" id="phone" name="phone" type="tel" value="{{ old('phone', $account['phone']) }}" autocomplete="tel" aria-describedby="phone-help @error('phone') phone-error @enderror">
                            <span id="phone-help" class="text-sm font-normal text-ink-muted">Used only when it is needed for your account.</span>
                            @error('phone') <span id="phone-error" class="text-sm font-normal text-[var(--wm-critical)]">{{ $message }}</span> @enderror
                        </label>
                    </div>
                </section>

                <section class="rounded-[var(--wm-radius-md)] border border-border bg-surface p-5 shadow-[var(--wm-shadow-card)] sm:p-7" aria-labelledby="profile-photo-heading">
                    <h2 id="profile-photo-heading" class="text-2xl text-ink">Profile photo</h2>
                    <p class="mt-3 text-sm text-ink-muted">Profile photos will be available when photo sharing is introduced. There is nothing to upload here yet.</p>
                </section>

                <section class="rounded-[var(--wm-radius-md)] border border-border bg-surface p-5 shadow-[var(--wm-shadow-card)] sm:p-7" aria-labelledby="communication-heading">
                    <h2 id="communication-heading" class="text-2xl text-ink">Communication preferences</h2>
                    <p class="mt-3 text-sm text-ink-muted">Choose the optional updates you would like to receive. Essential account and security messages are always sent.</p>
                    <fieldset class="mt-6 grid gap-4">
                        <legend class="sr-only">Optional email updates</legend>
                        @foreach ($preferences as $preference)
                            <label class="flex items-start gap-3 rounded-[var(--wm-radius-sm)] border border-border p-4" for="preference-{{ $preference['key'] }}">
                                <input class="mt-1 size-4 shrink-0 accent-brand" id="preference-{{ $preference['key'] }}" name="preferences[{{ $preference['key'] }}]" type="checkbox" value="1" @checked(old('preferences.'.$preference['key'], $preference['is_subscribed']))>
                                <span>
                                    <span class="block text-sm font-semibold text-ink">{{ $preference['label'] }}</span>
                                    <span class="mt-1 block text-sm text-ink-muted">{{ $preference['description'] }}</span>
                                </span>
                            </label>
                        @endforeach
                    </fieldset>
                    @error('preferences') <p class="mt-3 text-sm text-[var(--wm-critical)]">{{ $message }}</p> @enderror
                </section>

                <div class="flex flex-wrap items-center gap-4">
                    <x-public.button type="submit">Save settings</x-public.button>
                    <a class="text-sm font-semibold text-brand" href="{{ route('account.security.show') }}">Account security</a>
                    <a class="text-sm font-semibold text-brand" href="{{ route('new-here') }}">Back to New here?</a>
                </div>
            </form>
        </div>
    </section>
@endsection
