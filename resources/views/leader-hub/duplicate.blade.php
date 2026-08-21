@extends('layouts.account')

@section('title', 'Duplicate walk')

@section('content')
    <section class="bg-surface-raised py-10 sm:py-14">
        <div class="wm-container max-w-[var(--wm-container-copy)]">
            <a class="text-sm font-semibold text-brand underline decoration-brand/30 underline-offset-4" href="{{ route('leader-hub.index') }}">&larr; Leader Hub</a>
            <p class="mt-5 text-xs font-semibold uppercase tracking-[0.15em] text-brand">Duplicate walk</p>
            <h1 class="mt-2 text-4xl text-ink sm:text-5xl">Create a draft from {{ $source->event->title }}</h1>

            <form class="mt-8 grid gap-6" method="POST" action="{{ route('leader-hub.walks.duplicate.store', $source) }}">
                @csrf
                <section class="rounded-[var(--wm-radius-md)] border border-border bg-surface p-5 shadow-[var(--wm-shadow-card)] sm:p-7">
                    <div class="grid gap-5">
                        <label class="grid gap-1.5 text-sm font-semibold text-ink" for="title">New title
                            <input class="wm-form-control font-normal" id="title" name="title" type="text" value="{{ old('title', $source->event->title.' copy') }}" required>
                            @error('title') <span class="text-sm font-normal text-[var(--wm-critical)]">{{ $message }}</span> @enderror
                        </label>
                        <label class="grid gap-1.5 text-sm font-semibold text-ink" for="slug">New address
                            <input class="wm-form-control font-normal" id="slug" name="slug" type="text" value="{{ old('slug', $source->event->slug.'-copy') }}" required>
                            @error('slug') <span class="text-sm font-normal text-[var(--wm-critical)]">{{ $message }}</span> @enderror
                        </label>
                        <label class="grid gap-1.5 text-sm font-semibold text-ink" for="starts-at">New start
                            <input class="wm-form-control font-normal" id="starts-at" name="starts_at" type="datetime-local" value="{{ old('starts_at', $source->event->starts_at->format('Y-m-d\\TH:i')) }}" required>
                            @error('starts_at') <span class="text-sm font-normal text-[var(--wm-critical)]">{{ $message }}</span> @enderror
                        </label>
                        <label class="grid gap-1.5 text-sm font-semibold text-ink" for="ends-at">New end <span class="font-normal text-ink-muted">(optional)</span>
                            <input class="wm-form-control font-normal" id="ends-at" name="ends_at" type="datetime-local" value="{{ old('ends_at', $source->event->ends_at?->format('Y-m-d\\TH:i')) }}">
                            @error('ends_at') <span class="text-sm font-normal text-[var(--wm-critical)]">{{ $message }}</span> @enderror
                        </label>
                    </div>
                </section>

                <fieldset class="rounded-[var(--wm-radius-md)] border border-border bg-surface p-5 shadow-[var(--wm-shadow-card)] sm:p-7">
                    <legend class="px-1 text-2xl text-ink">Copy from the original</legend>
                    <div class="mt-4 grid gap-3 sm:grid-cols-2">
                        @foreach ($copyGroups as $value => $label)
                            <label class="flex items-center gap-3 rounded-[var(--wm-radius-sm)] border border-border p-3" for="copy-{{ $value }}">
                                <input class="size-4 accent-brand" id="copy-{{ $value }}" name="copy_groups[]" type="checkbox" value="{{ $value }}" @checked(in_array($value, old('copy_groups', $defaults), true))>
                                <span class="text-sm font-semibold text-ink">{{ $label }}</span>
                            </label>
                        @endforeach
                    </div>
                </fieldset>

                <div><x-public.button type="submit">Create draft</x-public.button></div>
            </form>
        </div>
    </section>
@endsection
