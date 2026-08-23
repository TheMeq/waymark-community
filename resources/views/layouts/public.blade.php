<!DOCTYPE html>
@php($seoMetadata = $seo ?? null)
<html
    lang="{{ str_replace('_', '-', app()->getLocale()) }}"
    style="@foreach ($theme->cssVariables() as $property => $value) {{ $property }}: {{ $value }}; @endforeach"
>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>{{ $seoMetadata?->title ?? trim($__env->yieldContent('title', config('app.name', 'Waymark Community'))) }}</title>
        <meta name="description" content="{{ $seoMetadata?->description ?? trim($__env->yieldContent('meta_description', 'Walks, weekends away and a welcoming local community.')) }}">
        <link rel="canonical" href="{{ $seoMetadata?->canonical ?? url()->current() }}">
        <meta name="robots" content="{{ $seoMetadata?->robots ?? 'index,follow' }}">
        <meta property="og:type" content="{{ $seoMetadata?->openGraphType ?? 'website' }}">
        <meta property="og:title" content="{{ $seoMetadata?->title ?? trim($__env->yieldContent('title', config('app.name', 'Waymark Community'))) }}">
        <meta property="og:description" content="{{ $seoMetadata?->description ?? trim($__env->yieldContent('meta_description', 'Walks, weekends away and a welcoming local community.')) }}">
        <meta property="og:url" content="{{ $seoMetadata?->canonical ?? url()->current() }}">
        @if($seoMetadata?->image)<meta property="og:image" content="{{ $seoMetadata->image }}">@endif
        @foreach($seoMetadata?->structuredData ?? [] as $structuredData)
            <script type="application/ld+json">{!! json_encode($structuredData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
        @endforeach
        <link rel="manifest" href="/manifest.webmanifest">
        <meta name="theme-color" content="{{ $theme->primaryColour }}">
        @if ($branding['favicon_url'])<link rel="icon" href="{{ $branding['favicon_url'] }}">@endif

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
        @stack('scripts')
    </body>
</html>
