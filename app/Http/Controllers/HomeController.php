<?php

namespace App\Http\Controllers;

use App\Domain\Content\Models\HomepageSection;
use App\Domain\Content\Models\Testimonial;
use App\Domain\Content\Presentation\PublicSeo;
use App\Domain\Content\Queries\HomepageNews;
use App\Domain\Content\Queries\PublicBranding;
use App\Domain\Content\Queries\PublicCmsPages;
use App\Domain\Content\Queries\VisibleHomepageSections;
use App\Domain\Content\Queries\VisibleTestimonials;
use App\Domain\Gallery\Queries\HomepageCommunityPhotos;
use App\Domain\Gallery\Queries\PublicCommunityPhotos;
use App\Domain\Holidays\Queries\PublicHolidaysQuery;
use App\Domain\Operations\Queries\CurrentSiteProfile;
use App\Domain\Operations\Support\BrandTheme;
use App\Domain\SiteMedia\Models\SiteMedia;
use App\Domain\SiteMedia\SiteMediaPresenter;
use App\Domain\Walks\Queries\PublicWalksQuery;
use App\ViewModels\HomepageViewModel;
use App\ViewModels\PublicHolidayCardViewModel;
use App\ViewModels\PublicWalkCardViewModel;
use Illuminate\Contracts\View\View;

final class HomeController
{
    public function __invoke(PublicWalksQuery $walks, PublicHolidaysQuery $holidays, HomepageCommunityPhotos $photos, PublicCommunityPhotos $publicPhotos, VisibleHomepageSections $sections, VisibleTestimonials $testimonials, PublicCmsPages $pages, PublicBranding $branding, HomepageNews $news, SiteMediaPresenter $mediaPresenter, CurrentSiteProfile $currentProfile): View
    {
        $siteProfile = $currentProfile->get();
        $homepageSections = $sections->get()->keyBy('section_key');
        $walkEvents = $walks->upcoming()->limit(3)->get();
        $walkSection = $homepageSections->get('whats_on');
        if ($walkSection?->content_mode === 'pinned') {
            $pinnedWalk = $walks->upcoming()->whereKey($walkSection->pinned_id)->first();
            if ($pinnedWalk !== null) {
                $walkEvents = collect([$pinnedWalk])->concat($walkEvents)->unique('id')->take(3)->values();
            }
        }
        $upcomingWalks = $walkEvents
            ->map(fn ($event) => PublicWalkCardViewModel::fromEvent($event, $siteProfile))
            ->all();

        $holidaySection = $homepageSections->get('holiday');
        $holiday = $holidaySection?->content_mode === 'pinned'
            ? $holidays->published()->currentOrUpcoming()->whereKey($holidaySection->pinned_id)->first()
            : null;
        $holiday ??= $holidays->upcoming()->first();

        $galleryPhotos = $photos->take();
        $gallerySection = $homepageSections->get('gallery');
        if ($gallerySection?->content_mode === 'pinned') {
            $pinnedPhoto = $publicPhotos->find((int) $gallerySection->pinned_id);
            if ($pinnedPhoto !== null) {
                $galleryPhotos = collect([$pinnedPhoto])->concat($galleryPhotos)->unique('id')->take(6)->values();
            }
        }
        $gallery = $galleryPhotos
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

        $heroSection = $homepageSections->get('hero');
        $heroOverrides = array_filter([
            'headline' => $heroSection?->heading,
            'summary' => $heroSection?->supporting_copy,
            'image_url' => $branding->get()['hero_url'],
        ], fn ($value): bool => filled($value));
        if ($heroSection?->content_mode === 'pinned') {
            $media = SiteMedia::query()->find($heroSection->pinned_id);
            $presentation = $media instanceof SiteMedia ? $mediaPresenter->present($media) : null;
            if ($presentation !== null) {
                $heroOverrides['image_url'] = $presentation->url;
                $heroOverrides['image_alt'] = $presentation->alt;
                $heroOverrides['focal_position'] = $presentation->objectPosition();
            }
        }
        $testimonialSection = $homepageSections->get('testimonial');
        $testimonial = $testimonialSection?->content_mode === 'pinned'
            ? Testimonial::query()->where('active', true)->find($testimonialSection->pinned_id)
            : null;
        $testimonial ??= $testimonials->get()->first();
        $joinSection = $homepageSections->get('join');
        $joinPage = $joinSection?->content_mode === 'pinned'
            ? $pages->query()->find($joinSection->pinned_id)
            : null;

        return view('home', [
            'homepage' => HomepageViewModel::live($siteProfile, $upcomingWalks, $holiday === null ? null : PublicHolidayCardViewModel::spotlight($holiday), $gallery, $testimonial?->quote, $heroOverrides),
            'homepageSections' => $homepageSections,
            'configuredSectionKeys' => HomepageSection::query()->pluck('section_key'),
            'homeNews' => $homeNews,
            'joinPage' => $joinPage,
            'theme' => BrandTheme::fromSiteProfile($siteProfile),
            'seo' => app(PublicSeo::class)->home($siteProfile),
        ]);
    }
}
