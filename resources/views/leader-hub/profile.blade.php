@extends('layouts.account')

@section('title', 'Leader profile')

@section('content')
    <section class="bg-surface-raised py-10 sm:py-14">
        <div class="wm-container max-w-[var(--wm-container-copy)]">
            <p class="text-xs font-semibold uppercase tracking-[0.15em] text-brand">Leader Hub</p>
            <h1 class="mt-2 text-4xl text-ink sm:text-5xl">Leader profile</h1>
            <p class="mt-4 text-ink-muted">Choose the small amount of information you want to share publicly as {{ $profile['display_name'] }}.</p>

            @if (session('status'))
                <p class="mt-6 rounded-[var(--wm-radius-sm)] border border-brand/30 bg-surface-soft p-4 text-sm text-ink" role="status">{{ session('status') }}</p>
            @endif

            <form class="mt-8 grid gap-6" method="POST" action="{{ route('leader-hub.profile.update') }}">
                @csrf
                @method('PATCH')

                <section class="rounded-[var(--wm-radius-md)] border border-border bg-surface p-5 shadow-[var(--wm-shadow-card)] sm:p-7" aria-labelledby="leader-profile-heading">
                    <h2 id="leader-profile-heading" class="text-2xl text-ink">Public profile</h2>
                    <label class="mt-6 flex items-start gap-3 rounded-[var(--wm-radius-sm)] border border-border p-4" for="public-profile-enabled">
                        <input class="mt-1 size-4 shrink-0 accent-brand" id="public-profile-enabled" name="public_profile_enabled" type="checkbox" value="1" @checked(old('public_profile_enabled', $profile['is_public']))>
                        <span><span class="block text-sm font-semibold text-ink">Show my walk leader profile publicly</span><span class="mt-1 block text-sm text-ink-muted">Only your public name, introduction and upcoming walks are shown.</span></span>
                    </label>

                    <div class="mt-6 grid gap-5">
                        <label class="grid gap-1.5 text-sm font-semibold text-ink" for="public-profile-slug">Profile address
                            <input class="wm-form-control font-normal" id="public-profile-slug" name="public_profile_slug" type="text" value="{{ old('public_profile_slug', $profile['slug']) }}" autocomplete="off" aria-describedby="public-profile-slug-help @error('public_profile_slug') public-profile-slug-error @enderror">
                            <span id="public-profile-slug-help" class="text-sm font-normal text-ink-muted">Use lowercase letters, numbers and hyphens.</span>
                            @error('public_profile_slug') <span id="public-profile-slug-error" class="text-sm font-normal text-[var(--wm-critical)]">{{ $message }}</span> @enderror
                        </label>
                        <label class="grid gap-1.5 text-sm font-semibold text-ink" for="public-profile-introduction">Short introduction <span class="font-normal text-ink-muted">(optional)</span>
                            <textarea class="wm-form-control min-h-28 font-normal" id="public-profile-introduction" name="public_profile_introduction" maxlength="500">{{ old('public_profile_introduction', $profile['introduction']) }}</textarea>
                            @error('public_profile_introduction') <span class="text-sm font-normal text-[var(--wm-critical)]">{{ $message }}</span> @enderror
                        </label>
                    </div>
                </section>

                <div class="flex flex-wrap items-center gap-4">
                    <x-public.button type="submit">Save leader profile</x-public.button>
                    <a class="text-sm font-semibold text-brand" href="{{ route('leader-hub.index') }}">Back to Leader Hub</a>
                </div>
            </form>
        </div>
    </section>
@endsection
