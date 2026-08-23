<?php

namespace App\Domain\Content\Support;

use App\Domain\Content\Models\FooterSection;
use App\Domain\Content\Models\HomepageSection;
use App\Domain\Content\Models\NavigationItem;
use App\Domain\Content\Models\Testimonial;
use App\Domain\Operations\Models\SiteProfile;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

final class PublicContentCache
{
    public const BRANDING = 'waymark.public.branding.v1';

    public const NAVIGATION = 'waymark.public.navigation.v1';

    public const FOOTER = 'waymark.public.footer.v1';

    public const HOMEPAGE_SECTIONS = 'waymark.public.homepage-sections.v1';

    public const TESTIMONIALS = 'waymark.public.testimonials.v1';

    public static function forgetFor(Model $model): void
    {
        $keys = match ($model::class) {
            SiteProfile::class => [self::BRANDING, self::NAVIGATION],
            NavigationItem::class => [self::NAVIGATION],
            FooterSection::class => [self::FOOTER],
            HomepageSection::class => [self::HOMEPAGE_SECTIONS],
            Testimonial::class => [self::TESTIMONIALS],
            default => [],
        };

        foreach ($keys as $key) {
            Cache::forget($key);
        }
    }
}
