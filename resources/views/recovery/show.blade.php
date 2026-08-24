<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>Disaster recovery — Waymark Community</title>
    <style>body{font:1rem/1.5 system-ui,sans-serif;max-width:44rem;margin:4rem auto;padding:0 1.5rem;color:#18221b}main{border:1px solid #cad2ca;border-radius:1rem;padding:2rem}label{display:block;margin-top:1rem;font-weight:600}input{display:block;width:100%;box-sizing:border-box;min-height:44px;margin-top:.35rem;padding:.65rem;border:1px solid #788378;border-radius:.5rem}button{margin-top:1.5rem;min-height:44px;padding:.65rem 1rem;border:0;border-radius:999px;background:#526b3f;color:white;font-weight:700}.error{color:#9f1d20}</style>
</head>
<body>
<main>
    <h1>Disaster recovery</h1>
    @if ($completed)
        <h2>Restore completed</h2>
        <p>Remove the uploaded backup from any temporary hosting area, then sign in and run System health checks.</p>
    @else
        <p>This standalone route is for a broken administration area. It replaces the current database, private files and restoration configuration.</p>
        @if ($errors->any())<p class="error">{{ $errors->first('recovery') }}</p>@endif
        <form method="post" action="{{ route('recovery.restore') }}" enctype="multipart/form-data">
            @csrf
            <label>Recovery token <input name="recovery_token" type="password" autocomplete="off" required></label>
            <label>Verified Waymark backup <input name="backup" type="file" required></label>
            <label>Encryption passphrase, if used <input name="passphrase" type="password" autocomplete="off"></label>
            <label>Type RESTORE WAYMARK <input name="confirmation" autocomplete="off" required></label>
            <button type="submit">Restore this backup</button>
        </form>
    @endif
</main>
</body>
</html>
