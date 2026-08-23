<?php

namespace App\Http\Controllers;

use App\Domain\Content\Queries\PublicSitemap;
use Illuminate\Http\Response;

final class SitemapController
{
    public function __invoke(PublicSitemap $sitemap): Response
    {
        return response()->view('seo.sitemap', ['entries' => $sitemap->entries()], 200, ['Content-Type' => 'application/xml']);
    }
}
