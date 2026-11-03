<?php

namespace App\Domain\SiteMedia\Enums;

enum SiteMediaPurpose: string
{
    case Library = 'library';
    case ContentImage = 'content_image';
    case WalkFeaturedImage = 'walk_featured_image';
    case SiteLogo = 'site_logo';
    case SiteFavicon = 'site_favicon';
    case SiteHero = 'site_hero';
    case SiteAffiliationLogo = 'site_affiliation_logo';

    public function requiredDecorativeState(): ?bool
    {
        return match ($this) {
            self::Library => null,
            self::WalkFeaturedImage, self::ContentImage => false,
            self::SiteLogo, self::SiteFavicon, self::SiteHero, self::SiteAffiliationLogo => true,
        };
    }
}
