@props(['branding'])

<a class="inline-flex min-h-11 items-center hover:text-brand" href="{{ $branding['affiliation_url'] }}" rel="noopener">
    @if ($branding['affiliation_logo_url'] ?? null)
        <img class="h-10 w-auto max-w-36 object-contain" src="{{ $branding['affiliation_logo_url'] }}" alt="{{ $branding['affiliation_name'] }}">
    @else
        {{ $branding['affiliation_name'] }}
    @endif
</a>
