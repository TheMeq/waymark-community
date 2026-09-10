<?php

namespace App\Domain\SiteMedia\Enums;

enum SiteMediaPurpose: string
{
    case Library = 'library';
    case WalkFeaturedImage = 'walk_featured_image';
    case SiteLogo = 'site_logo';
    case SiteFavicon = 'site_favicon';

    public function requiredDecorativeState(): ?bool
    {
        return match ($this) {
            self::Library => null,
            self::WalkFeaturedImage => false,
            self::SiteLogo, self::SiteFavicon => true,
        };
    }
}
