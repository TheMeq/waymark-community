@props(['site'])

<header {{ $attributes->class('border-b border-border bg-surface-raised') }}>
    <div class="wm-container flex min-h-20 items-center justify-between gap-5 py-3">
        <a class="inline-flex items-center gap-3 text-base font-semibold text-ink no-underline" href="/" aria-label="{{ $site['name'] }} home">
            <span class="grid size-10 place-items-center rounded-full bg-brand text-lg font-semibold text-on-brand" aria-hidden="true">W</span>
            <span>{{ $site['name'] }}</span>
        </a>

        <details class="group relative lg:hidden">
            <summary class="grid min-h-11 min-w-11 cursor-pointer list-none place-items-center rounded-full border border-border bg-surface-raised text-ink [&::-webkit-details-marker]:hidden" aria-label="Open navigation">
                <svg class="size-5 group-open:hidden" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                    <path d="M4 7h16M4 12h16M4 17h16" stroke-linecap="round" />
                </svg>
                <svg class="hidden size-5 group-open:block" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                    <path d="m6 6 12 12M18 6 6 18" stroke-linecap="round" />
                </svg>
            </summary>

            <nav class="absolute right-0 z-50 mt-3 w-[min(20rem,calc(100vw-2rem))] rounded-[var(--wm-radius-md)] border border-border bg-surface-raised p-4 shadow-[var(--wm-shadow-float)]" aria-label="Mobile navigation">
                <ul class="grid gap-2 text-sm font-medium">
                    <li><x-public.button class="w-full" href="/walks" variant="secondary">Upcoming walks</x-public.button></li>
                    <li><x-public.button class="w-full" href="/join">Join us</x-public.button></li>
                    <li><x-public.button class="w-full" href="/account" variant="quiet">Account</x-public.button></li>
                    <li class="mt-2 border-t border-border pt-3"><a class="block rounded-[var(--wm-radius-sm)] px-4 py-2 hover:bg-surface-soft" href="/about">About</a></li>
                    <li><a class="block rounded-[var(--wm-radius-sm)] px-4 py-2 hover:bg-surface-soft" href="/photos">Photos</a></li>
                </ul>
            </nav>
        </details>

        <nav class="hidden lg:block" aria-label="Primary navigation">
            <ul class="flex items-center justify-end gap-x-6 text-sm font-medium">
                <li><a class="hover:text-brand" href="/walks">Upcoming walks</a></li>
                <li><a class="hover:text-brand" href="/about">About</a></li>
                <li><a class="hover:text-brand" href="/photos">Photos</a></li>
                <li><a class="hover:text-brand" href="/account">Account</a></li>
                <li><x-public.button href="/join">Join us</x-public.button></li>
            </ul>
        </nav>
    </div>
</header>
