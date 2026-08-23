<?php

namespace App\Http\Controllers;

use App\Domain\Content\Queries\VisibleHomepageSections;
use App\Domain\Content\Queries\VisibleTestimonials;
use App\Domain\Gallery\Queries\HomepageCommunityPhotos;
use App\Domain\Holidays\Queries\PublicHolidaysQuery;
use App\Domain\Operations\Models\SiteProfile;
use App\Domain\Operations\Support\BrandTheme;
use App\Domain\Walks\Queries\PublicWalksQuery;
use App\ViewModels\HomepageViewModel;
use App\ViewModels\PublicHolidayCardViewModel;
use App\ViewModels\PublicWalkCardViewModel;
use Illuminate\Contracts\View\View;

final class HomeController
{
    public function __invoke(PublicWalksQuery $walks, PublicHolidaysQuery $holidays, HomepageCommunityPhotos $photos, VisibleHomepageSections $sections, VisibleTestimonials $testimonials): View
    {
        $siteProfile = SiteProfile::query()->find(SiteProfile::SINGLETON_ID) ?? new SiteProfile;
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

        return view('home', [
            'homepage' => HomepageViewModel::demo($weekendWalks, $holiday === null ? null : PublicHolidayCardViewModel::spotlight($holiday), $gallery, $testimonials->get()->first()?->quote),
            'homepageSections' => $sections->get()->keyBy('section_key'),
            'theme' => BrandTheme::fromSiteProfile($siteProfile),
        ]);
    }
}
