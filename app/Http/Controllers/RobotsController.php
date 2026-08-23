<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;

final class RobotsController
{
    public function __invoke(): Response
    {
        return response("User-agent: *\nAllow: /\nSitemap: ".url('/sitemap.xml')."\n", 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }
}
