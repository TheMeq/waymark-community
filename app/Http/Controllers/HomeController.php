<?php

namespace App\Http\Controllers;

use App\Domain\Content\Queries\HomepageNews;
use App\Domain\Content\Queries\VisibleHomepageSections;
use App\Domain\Content\Queries\VisibleTestimonials;
use App\Domain\Gallery\Queries\HomepageCommunityPhotos;
use App\Domain\Holidays\Queries\PublicHolidaysQuery;
use App\Domain\Operations\Models\SiteProfile;
use App\Domain\Operations\Support\BrandTheme;
use App\Domain\SiteMedia\SiteMediaPresenter;
use App\Domain\Walks\Queries\PublicWalksQuery;
use App\ViewModels\HomepageViewModel;
use App\ViewModels\PublicHolidayCardViewModel;
use App\ViewModels\PublicWalkCardViewModel;
use Illuminate\Contracts\View\View;

final class HomeController
{
    public function __invoke(PublicWalksQuery $walks, PublicHolidaysQuery $holidays, HomepageCommunityPhotos $photos, VisibleHomepageSections $sections, VisibleTestimonials $testimonials, HomepageNews $news, SiteMediaPresenter $mediaPresenter): View
    {
        $siteProfile = SiteProfile::query()->find(SiteProfile::SINGLETON_ID) ?? new SiteProfile;
        $homepageSections = $sections->get()->keyBy('section_key');
        $weekendWalks = $walks->weekend()
            ->limit(3)
            ->get()
            ->map(fn ($event) => PublicWalkCardViewModel::fromEvent($event, $siteProfile))
            ->all();

        $holiday = $holidays->upcoming()->first();
        $gallery = $photos->take()
            ->map(fn ($photo): array => [
                'image_url' => $photo->imageUrl,
                'image_alt' => $photo->caption ?? $photo->contextLabel ?? 'Community photo',
                'detail_url' => $photo->detailUrl,
                'width' => (string) $photo->width,
                'height' => (string) $photo->height,
                'rotation_style' => $photo->rotationStyle,
            ])
            ->all();
        $homeNews = $homepageSections->has('news')
            ? $news->get($homepageSections['news'])->map(function ($article) use ($mediaPresenter): array {
                $image = $article->featuredMedia === null ? null : $mediaPresenter->present($article->featuredMedia);

                return [
                    'title' => $article->title,
                    'summary' => $article->summary,
                    'category' => $article->primary_category,
                    'date' => $article->publish_at?->format('j M Y'),
                    'url' => route('news.show', $article->slug),
                    'image' => $image,
                ];
            })->all()
            : [];

        return view('home', [
            'homepage' => HomepageViewModel::demo($weekendWalks, $holiday === null ? null : PublicHolidayCardViewModel::spotlight($holiday), $gallery, $testimonials->get()->first()?->quote),
            'homepageSections' => $homepageSections,
            'homeNews' => $homeNews,
            'theme' => BrandTheme::fromSiteProfile($siteProfile),
        ]);
    }
}
