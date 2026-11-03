<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" style="@foreach ($theme->cssVariables() as $property => $value) {{ $property }}: {{ $value }}; @endforeach">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="{{ $theme->primaryColour }}">
    <title>Offline | {{ $site['name'] }}</title>
    <style>
        :root { color-scheme: light; --wm-surface: #f7f6f1; --wm-surface-raised: #fff; --wm-text: #1f241e; --wm-text-muted: #62695f; --wm-border: #dce1d7; --wm-radius-lg: 1.5rem; --wm-radius-pill: 999px; --wm-shadow-card: 0 12px 36px rgb(31 38 28 / .08); }
        * { box-sizing: border-box; }
        body { min-width: 0; margin: 0; background: var(--wm-surface); color: var(--wm-text); font-family: ui-sans-serif, system-ui, sans-serif; line-height: 1.6; }
        main { width: min(calc(100% - 2rem), 42rem); min-height: 100vh; display: grid; place-items: center; margin: 0 auto; padding: 3rem 0; }
        article { width: 100%; border: 1px solid var(--wm-border); border-radius: var(--wm-radius-lg); background: var(--wm-surface-raised); padding: clamp(1.5rem, 6vw, 3rem); box-shadow: var(--wm-shadow-card); }
        p { margin: 0; } .eyebrow { color: var(--wm-brand); font-size: .75rem; font-weight: 700; letter-spacing: .15em; text-transform: uppercase; }
        h1 { margin: .75rem 0 0; font-size: clamp(2.25rem, 9vw, 3.5rem); letter-spacing: -.04em; line-height: 1.08; }
        .copy { margin-top: 1rem; color: var(--wm-text-muted); font-size: 1.125rem; }
        a { display: inline-flex; min-height: 44px; align-items: center; justify-content: center; margin-top: 1.5rem; border: 1px solid var(--wm-brand); border-radius: var(--wm-radius-pill); background: var(--wm-brand); padding: .625rem 1.25rem; color: var(--wm-on-brand); font-weight: 700; text-decoration: none; }
        a:focus-visible { outline: 3px solid color-mix(in srgb, var(--wm-brand) 38%, transparent); outline-offset: 3px; }
    </style>
</head>
<body data-pwa-offline>
    <main>
        <article>
            <p class="eyebrow">{{ $site['name'] }}</p>
            <h1>You're offline</h1>
            <p class="copy">Try again when you're back online.</p>
            <a href="{{ route('home', absolute: false) }}">Try again</a>
        </article>
    </main>
</body>
</html>
