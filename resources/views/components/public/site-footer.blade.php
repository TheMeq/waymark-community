@props(['site'])

<footer {{ $attributes->class('bg-surface-strong py-10 text-[var(--wm-text-inverse)]') }}>
    <div class="wm-container grid gap-8 md:grid-cols-[1fr_auto] md:items-end">
        <div>
            <a class="inline-flex items-center gap-3 font-semibold text-white" href="/" aria-label="{{ $site['name'] }} home">
                <span class="grid size-9 place-items-center rounded-full bg-brand text-on-brand" aria-hidden="true">W</span>
                <span>{{ $site['name'] }}</span>
            </a>
            <p class="mt-3 max-w-md text-sm text-white/70">Good walks, shared well.</p>
        </div>

        <nav aria-label="Footer navigation">
            <ul class="flex flex-wrap gap-x-5 gap-y-2 text-sm text-white/80">
                <li><a class="hover:text-white" href="/privacy">Privacy</a></li>
                <li><a class="hover:text-white" href="/accessibility">Accessibility</a></li>
                <li><a class="hover:text-white" href="/contact">Contact</a></li>
            </ul>
        </nav>
    </div>
</footer>
