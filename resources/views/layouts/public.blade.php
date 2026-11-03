<!DOCTYPE html>
@php($seoMetadata = $seo ?? null)
@php($serviceWorkerUrl = \App\Domain\Content\Presentation\PublicUrl::route('pwa.service-worker'))
@php($serviceWorkerScope = (rtrim(\App\Domain\Content\Presentation\PublicUrl::route('home'), '/') ?: '').'/')
@php($manifestUrl = \App\Domain\Content\Presentation\PublicUrl::route('pwa.manifest'))
<html
    lang="{{ str_replace('_', '-', app()->getLocale()) }}"
    style="@foreach ($theme->cssVariables() as $property => $value) {{ $property }}: {{ $value }}; @endforeach"
    data-service-worker-url="{{ $serviceWorkerUrl }}"
    data-service-worker-scope="{{ $serviceWorkerScope }}"
>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>{{ $seoMetadata?->title ?? trim($__env->yieldContent('title', config('app.name', 'Waymark Community'))) }}</title>
        <meta name="description" content="{{ $seoMetadata?->description ?? trim($__env->yieldContent('meta_description', 'Walks, weekends away and a welcoming local community.')) }}">
        <link rel="canonical" href="{{ $seoMetadata?->canonical ?? url()->current() }}">
        <meta name="robots" content="{{ $staging ? 'noindex,nofollow' : ($seoMetadata?->robots ?? 'index,follow') }}">
        <meta property="og:type" content="{{ $seoMetadata?->openGraphType ?? 'website' }}">
        <meta property="og:title" content="{{ $seoMetadata?->title ?? trim($__env->yieldContent('title', config('app.name', 'Waymark Community'))) }}">
        <meta property="og:description" content="{{ $seoMetadata?->description ?? trim($__env->yieldContent('meta_description', 'Walks, weekends away and a welcoming local community.')) }}">
        <meta property="og:url" content="{{ $seoMetadata?->canonical ?? url()->current() }}">
        @if($seoMetadata?->image)<meta property="og:image" content="{{ $seoMetadata->image }}">@endif
        @foreach($seoMetadata?->structuredData ?? [] as $structuredData)
            <script type="application/ld+json">{!! json_encode($structuredData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
        @endforeach
        <link rel="manifest" href="{{ $manifestUrl }}">
        <meta name="theme-color" content="{{ $theme->primaryColour }}">
        @if ($branding['favicon_url'])<link rel="icon" href="{{ $branding['favicon_url'] }}"@if($branding['favicon_type'] ?? null) type="{{ $branding['favicon_type'] }}"@endif>@endif

        @vite(['resources/css/app.css', 'resources/js/app.js'])
        <x-public.analytics :analytics="$analytics" />
        @stack('head')
    </head>
    <body>
        <a class="wm-skip-link" href="#main-content">Skip to main content</a>

        @yield('site-header')

        <main id="main-content" tabindex="-1">
            @yield('content')
        </main>

        @yield('site-footer')
        <x-public.cookie-banner :cookie-preferences="$cookiePreferences" />
        @stack('scripts')
    </body>
</html>
