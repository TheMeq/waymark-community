<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>Maintenance — {{ $maintenance['group_name'] }}</title>
    <style>:root{--primary:{{ $maintenance['primary_colour'] }};--accent:{{ $maintenance['accent_colour'] }}}body{margin:0;min-height:100vh;display:grid;place-items:center;background:#f4f1e9;color:#1d261f;font:1rem/1.6 system-ui,sans-serif;padding:1.5rem}main{width:min(38rem,100%);box-sizing:border-box;background:white;border:1px solid #d8d8cf;border-radius:1.5rem;padding:clamp(2rem,7vw,4rem);box-shadow:0 1rem 3rem #23301f14}.mark{width:3rem;height:.35rem;border-radius:999px;background:var(--accent)}h1{font-size:clamp(2rem,6vw,3.5rem);line-height:1.05;color:var(--primary)}a{color:var(--primary);font-weight:700}</style>
</head>
<body>
<main>
    <div class="mark" aria-hidden="true"></div>
    <p>{{ $maintenance['group_name'] }}</p>
    <h1>We’ll be back on the trail shortly.</h1>
    <p>{{ $maintenance['message'] }}</p>
    @if ($maintenance['expected_return_at'])<p><strong>Expected back:</strong> <time datetime="{{ $maintenance['expected_return_at'] }}">{{ \Illuminate\Support\Carbon::parse($maintenance['expected_return_at'])->timezone(config('app.timezone'))->format('j M Y, H:i') }}</time></p>@endif
    @if ($maintenance['contact_url'])<p><a href="{{ $maintenance['contact_url'] }}">Contact or status information</a></p>@endif
</main>
</body>
</html>
