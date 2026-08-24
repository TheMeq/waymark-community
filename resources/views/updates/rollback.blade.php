<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <meta name="referrer" content="no-referrer">
    <title>Finish update rollback — Waymark Community</title>
    <style>body{font:1rem/1.5 system-ui,sans-serif;max-width:44rem;margin:4rem auto;padding:0 1.5rem;color:#18221b}main{border:1px solid #cad2ca;border-radius:1rem;padding:2rem}button{margin-top:1.5rem;min-height:44px;padding:.65rem 1rem;border:0;border-radius:999px;background:#526b3f;color:white;font-weight:700}</style>
</head>
<body>
<main>
    <h1>Finish restoring the previous release</h1>
    <p>The previous application files are restored. Continue to restore and verify its database under a fresh request.</p>
    <form method="post" action="{{ route('updates.rollback.store') }}">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">
        <button type="submit">Complete rollback</button>
    </form>
</main>
</body>
</html>
