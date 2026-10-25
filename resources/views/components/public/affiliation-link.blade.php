@props(['branding'])
@if (($branding['affiliation_url'] ?? null) && (($branding['affiliation_name'] ?? '') !== '' || ($branding['affiliation_logo_url'] ?? null)))
    <a {{ $attributes->class('inline-flex min-h-11 items-center justify-center') }} href="{{ $branding['affiliation_url'] }}" rel="noopener" aria-label="{{ $branding['affiliation_name'] ?: 'Affiliated organisation' }}">
        @if ($branding['affiliation_logo_url'] ?? null)
            <img class="h-10 w-auto max-w-24 object-contain sm:max-w-28" src="{{ $branding['affiliation_logo_url'] }}" alt="{{ $branding['affiliation_name'] ?: 'Affiliated organisation' }}">
        @else
            <span class="max-w-28 break-words text-sm">{{ $branding['affiliation_name'] }}</span>
        @endif
    </a>
@endif
