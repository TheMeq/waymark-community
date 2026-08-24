<?php

declare(strict_types=1);

require __DIR__.'/waymark-update-recovery-runtime.php';

$completed = false;
$error = false;
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    try {
        WaymarkEmergencyUpdateRollback::restore(
            dirname(__DIR__).'/storage/app/private/update-state.json',
            is_string($_POST['activation_token'] ?? null) ? $_POST['activation_token'] : '',
        );
        $completed = true;
    } catch (Throwable) {
        $error = true;
        http_response_code(403);
    }
}
header('Content-Type: text/html; charset=UTF-8');
header('X-Robots-Tag: noindex, nofollow');
?>
<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Waymark update recovery</title>
<style>body{font:1rem/1.5 system-ui,sans-serif;max-width:44rem;margin:4rem auto;padding:0 1.5rem;color:#18221b}main{border:1px solid #cad2ca;border-radius:1rem;padding:2rem}label{display:block;font-weight:600}input{display:block;width:100%;box-sizing:border-box;min-height:44px;margin-top:.35rem;padding:.65rem;border:1px solid #788378;border-radius:.5rem}button{margin-top:1.5rem;min-height:44px;padding:.65rem 1rem;border:0;border-radius:999px;background:#526b3f;color:white;font-weight:700}.error{color:#9f1d20}</style></head>
<body><main><h1>Waymark update recovery</h1>
<?php if ($completed) { ?><h2>Previous application files restored</h2><p>Maintenance remains active. Open <code>/recovery</code> to verify the restored installation.</p>
<?php } else { ?><p>Use this only when a newly activated release cannot boot the normal Waymark recovery route.</p>
<?php if ($error) { ?><p class="error">Update recovery could not be authorised or completed.</p><?php } ?>
<form method="post"><label>Pending update activation token<input type="password" name="activation_token" autocomplete="off" required></label><button type="submit">Restore previous application files</button></form>
<?php } ?></main></body></html>
