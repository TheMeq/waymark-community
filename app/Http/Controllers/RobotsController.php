<?php

namespace App\Http\Controllers;

use App\Domain\Operations\Environment\StagingMode;
use Illuminate\Http\Response;

final class RobotsController
{
    public function __invoke(StagingMode $staging): Response
    {
        if ($staging->active()) {
            return response("User-agent: *\nDisallow: /\n", 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
        }

        return response("User-agent: *\nAllow: /\nSitemap: ".url('/sitemap.xml')."\n", 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }
}
