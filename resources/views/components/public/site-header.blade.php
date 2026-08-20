@props(['site'])

<header {{ $attributes->class('border-b border-border bg-surface-raised') }}>
    <div class="wm-container flex min-h-20 flex-wrap items-center justify-between gap-4 py-3">
        <a class="inline-flex items-center gap-3 text-base font-semibold text-ink no-underline" href="/" aria-label="{{ $site['name'] }} home">
            <span class="grid size-10 place-items-center rounded-full bg-brand text-lg font-semibold text-on-brand" aria-hidden="true">W</span>
            <span>{{ $site['name'] }}</span>
        </a>

        <nav aria-label="Primary navigation">
            <ul class="flex flex-wrap items-center justify-end gap-x-5 gap-y-2 text-sm font-medium">
                <li><a class="hover:text-brand" href="/walks">Upcoming walks</a></li>
                <li><a class="hover:text-brand" href="/about">About</a></li>
                <li><a class="hover:text-brand" href="/photos">Photos</a></li>
                <li><a class="hover:text-brand" href="/account">Account</a></li>
                <li><x-public.button href="/join">Join us</x-public.button></li>
            </ul>
        </nav>
    </div>
</header>
