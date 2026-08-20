<!DOCTYPE html>
<html
    lang="{{ str_replace('_', '-', app()->getLocale()) }}"
    style="@foreach ($theme->cssVariables() as $property => $value) {{ $property }}: {{ $value }}; @endforeach"
>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>@yield('title', config('app.name', 'Waymark Community'))</title>
        <meta name="description" content="@yield('meta_description', 'Walks, weekends away and a welcoming local community.')">

        @vite(['resources/css/app.css', 'resources/js/app.js'])
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
