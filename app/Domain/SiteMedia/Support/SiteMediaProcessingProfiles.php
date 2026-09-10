<?php

namespace App\Domain\SiteMedia\Support;

use App\Domain\Gallery\Contracts\RasterImageTransformer;
use App\Domain\Gallery\Data\ImageProcessingConfiguration;
use App\Domain\SiteMedia\Enums\SiteMediaPurpose;

final readonly class SiteMediaProcessingProfiles
{
    public function __construct(private RasterImageTransformer $transformer) {}

    public function configurationFor(SiteMediaPurpose $purpose): ImageProcessingConfiguration
    {
        $base = (array) config('gallery.processing', []);
        $profile = (array) config('site-media.profiles.'.$purpose->value, []);

        return ImageProcessingConfiguration::from(array_replace($base, $profile), $this->transformer);
    }

    /** @return array<string> */
    public function requiredVariantsFor(SiteMediaPurpose $purpose): array
    {
        return match ($purpose) {
            SiteMediaPurpose::Library,
            SiteMediaPurpose::WalkFeaturedImage,
            SiteMediaPurpose::SiteLogo => ['master', 'large', 'medium', 'thumbnail'],
            SiteMediaPurpose::SiteFavicon => ['favicon'],
        };
    }
}
