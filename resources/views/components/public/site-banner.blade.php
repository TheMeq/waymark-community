@props(['banner'])

<aside
    {{ $attributes->class('border-b border-white/10 bg-surface-strong text-white') }}
    x-data="siteBanner(@js($banner['version']))"
    x-show="visible"
    x-transition.opacity.duration.150ms
    data-banner-version="{{ $banner['version'] }}"
    aria-label="Site announcement"
>
    <div class="wm-container flex min-h-12 items-center justify-center gap-4 py-2 text-center text-sm">
        <p>
            {{ $banner['message'] }}
            @if (! empty($banner['action_label']) && ! empty($banner['action_url']))
                <a class="ml-1 font-semibold text-white underline decoration-white/50 underline-offset-4 hover:decoration-white" href="{{ $banner['action_url'] }}">
                    {{ $banner['action_label'] }}
                </a>
            @endif
        </p>

        <button class="wm-js-only -mr-2 grid min-h-9 min-w-9 shrink-0 place-items-center rounded-full text-white/80 hover:bg-white/10 hover:text-white" type="button" x-on:click="dismiss" aria-label="Dismiss announcement">
            <svg class="size-4" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="m6 6 12 12M18 6 6 18" stroke-linecap="round" />
            </svg>
        </button>
    </div>
</aside>
