<?php

namespace App\Http\Controllers;

use App\Domain\Content\Models\NewsArticle;
use App\Domain\Content\Presentation\PublicSeo;
use App\Domain\Content\Queries\PublicNews;
use App\Domain\Content\Support\CmsBlockPresenter;
use App\Domain\Operations\Models\SiteProfile;
use App\Domain\Operations\Support\BrandTheme;
use App\Domain\SiteMedia\SiteMediaPresenter;
use Illuminate\View\View;

final class PublicNewsController
{
    public function index(PublicNews $news): View
    {
        return view('news.index', [...$this->site(), 'articles' => $news->active()->paginate(12)]);
    }

    public function show(string $slug, PublicNews $news, SiteMediaPresenter $mediaPresenter): View
    {
        /** @var NewsArticle $article */
        $article = $news->archive()->where('slug', $slug)->firstOrFail();

        $featuredImage = $article->featuredMedia === null ? null : $mediaPresenter->present($article->featuredMedia);

        return view('news.show', [...$this->site(), 'article' => $article, 'blocks' => app(CmsBlockPresenter::class)->present($article->blocks), 'featuredImage' => $featuredImage, 'seo' => app(PublicSeo::class)->news($article, $featuredImage?->url)]);
    }

    private function site(): array
    {
        $profile = SiteProfile::query()->find(SiteProfile::SINGLETON_ID) ?? new SiteProfile;

        return ['site' => ['name' => $profile->group_name ?? 'Waymark Community', 'strapline' => 'A local walking community'], 'theme' => BrandTheme::fromSiteProfile($profile)];
    }
}
