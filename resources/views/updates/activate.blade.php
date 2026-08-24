<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>Finish update — Waymark Community</title>
    <style>body{font:1rem/1.5 system-ui,sans-serif;max-width:44rem;margin:4rem auto;padding:0 1.5rem;color:#18221b}main{border:1px solid #cad2ca;border-radius:1rem;padding:2rem}button{margin-top:1.5rem;min-height:44px;padding:.65rem 1rem;border:0;border-radius:999px;background:#526b3f;color:white;font-weight:700}</style>
</head>
<body>
<main>
    <h1>Finish updating to Waymark Community {{ $version }}</h1>
    <p>The verified release files are active. Continue to boot and validate them in this fresh request before reopening the site.</p>
    <form method="post" action="{{ route('updates.activate.store') }}">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">
        <button type="submit">Complete update</button>
    </form>
</main>
</body>
</html>
