<?php

namespace Tests\Unit\Operations;

use App\Domain\Operations\Models\SiteProfile;
use App\Domain\Operations\Support\BrandTheme;
use PHPUnit\Framework\TestCase;

final class BrandThemeTest extends TestCase
{
    public function test_missing_installation_colours_use_the_waymark_fallback_theme(): void
    {
        $theme = BrandTheme::fromSiteProfile(new SiteProfile);

        $this->assertSame('#526B3F', $theme->primaryColour);
        $this->assertSame('#FFFFFF', $theme->primaryForeground);
        $this->assertSame('#D6B269', $theme->accentColour);
        $this->assertSame('#1F261C', $theme->accentForeground);
    }

    public function test_installation_colours_receive_a_contrast_safe_foreground(): void
    {
        $theme = BrandTheme::fromSiteProfile(new SiteProfile([
            'primary_colour' => '#F7E8B5',
            'accent_colour' => '#20351C',
        ]));

        $this->assertSame('#F7E8B5', $theme->primaryColour);
        $this->assertSame('#1F261C', $theme->primaryForeground);
        $this->assertSame('#20351C', $theme->accentColour);
        $this->assertSame('#FFFFFF', $theme->accentForeground);
    }

    public function test_invalid_installation_colours_fall_back_without_reaching_css(): void
    {
        $theme = BrandTheme::fromSiteProfile(new SiteProfile([
            'primary_colour' => 'not-a-colour',
            'accent_colour' => '#12',
        ]));

        $this->assertSame([
            '--wm-brand' => '#526B3F',
            '--wm-on-brand' => '#FFFFFF',
            '--wm-accent' => '#D6B269',
            '--wm-on-accent' => '#1F261C',
        ], $theme->cssVariables());
    }

    public function test_contrast_guidance_uses_the_same_safe_foreground_calculation(): void
    {
        $this->assertNull(BrandTheme::contrastGuidance('#000000'));
        $this->assertSame(
            'Contrast may be weak in some components.',
            BrandTheme::contrastGuidance('#777777'),
        );
    }
}
