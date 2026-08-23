<?php

namespace App\Http\Controllers;

use App\Domain\Content\Queries\PublicSitemap;
use App\Domain\Operations\Environment\StagingMode;
use Illuminate\Http\Response;

final class SitemapController
{
    public function __invoke(PublicSitemap $sitemap, StagingMode $staging): Response
    {
        abort_if($staging->active(), 404);

        return response()->view('seo.sitemap', ['entries' => $sitemap->entries()], 200, ['Content-Type' => 'application/xml']);
    }
}
