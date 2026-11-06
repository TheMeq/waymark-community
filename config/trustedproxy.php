<?php

return [
    // Local HTTPS proxies (for example Caddy or Cloudflare Tunnel) forward the
    // browser's scheme over HTTP. Other proxy addresses/CIDRs must be explicit.
    'proxies' => env('TRUSTED_PROXIES', '127.0.0.1,::1'),
];
