@props(['site', 'brandingOverride' => null])
@php($activeBranding = $brandingOverride ?? $branding)
@php($siteName = $activeBranding['name'] ?? $site['name'])

<footer {{ $attributes->class('bg-surface-strong py-4 text-[var(--wm-text-inverse)] lg:py-2.5') }}>
    <div class="wm-container grid gap-8 md:grid-cols-[1fr_auto] md:items-end">
        <div class="sm:flex sm:items-center sm:gap-5">
            <a class="inline-flex items-center gap-3 font-semibold text-white" href="{{ route('home') }}" aria-label="{{ $siteName }} home">
                @if ($activeBranding['logo_url'] ?? null)<img class="size-9 rounded-full object-contain lg:size-8" src="{{ $activeBranding['logo_url'] }}" alt="">@else<span class="grid size-9 place-items-center rounded-full bg-brand text-on-brand lg:size-8" aria-hidden="true">{{ mb_substr($activeBranding['short_name'] ?? 'W', 0, 2) }}</span>@endif
                <span>{{ $siteName }}</span>
            </a>
            <p class="mt-2 max-w-md text-xs text-white/70 sm:mt-0">Good walks, shared well.</p>
        </div>

        <nav aria-label="Footer navigation">
            <ul class="flex flex-wrap gap-x-5 gap-y-2 text-sm text-white/80">
                @forelse ($footerSections->flatMap(fn ($section) => $section->links) as $link)
                    <li><a class="hover:text-white" href="{{ $link['url'] }}">{{ $link['label'] }}</a></li>
                @empty
                    <li><a class="hover:text-white" href="{{ route('policies.show', 'privacy') }}">Privacy</a></li>
                    <li><a class="hover:text-white" href="{{ route('policies.show', 'accessibility') }}">Accessibility</a></li>
                    <li><a class="hover:text-white" href="{{ route('contact.create') }}">Contact</a></li>
                @endforelse
                @foreach ($activeBranding['social_links'] ?? [] as $social)<li><a class="hover:text-white" href="{{ $social['url'] }}" rel="noopener">{{ $social['label'] }}</a></li>@endforeach
                @if (($activeBranding['affiliation_name'] ?? '') !== '' && ($activeBranding['affiliation_url'] ?? null))<li><a class="hover:text-white" href="{{ $activeBranding['affiliation_url'] }}" rel="noopener">{{ $activeBranding['affiliation_name'] }}</a></li>@endif
            </ul>
        </nav>
    </div>
</footer>
