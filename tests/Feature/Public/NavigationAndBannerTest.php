<?php

namespace Tests\Feature\Public;

use Tests\TestCase;

final class NavigationAndBannerTest extends TestCase
{
    public function test_header_has_distinct_desktop_and_native_mobile_navigation(): void
    {
        $view = $this->blade(
            '<x-public.site-header :site="$site" />',
            ['site' => ['name' => 'Waymark Community']],
        );

        $view->assertSee('aria-label="Primary navigation"', false)
            ->assertSee('<details', false)
            ->assertSee('<summary', false)
            ->assertSee('aria-label="Open navigation"', false)
            ->assertSee('aria-label="Mobile navigation"', false)
            ->assertSeeInOrder(['Upcoming walks', 'Join us', 'Account']);
    }

    public function test_mobile_navigation_remains_operable_without_javascript(): void
    {
        $view = $this->blade(
            '<x-public.site-header :site="$site" />',
            ['site' => ['name' => 'Waymark Community']],
        );

        $view->assertSee('<details', false)
            ->assertDontSee('x-data=', false)
            ->assertDontSee('x-show=', false);
    }

    public function test_banner_exposes_its_version_and_stays_visible_without_javascript(): void
    {
        $view = $this->blade(
            '<x-public.site-banner :banner="$banner" />',
            [
                'banner' => [
                    'version' => 'summer-2026-v2',
                    'message' => 'Booking is open for the summer weekend away.',
                    'action_label' => 'See the weekend',
                    'action_url' => '/weekends/summer',
                ],
            ],
        );

        $view->assertSee('data-banner-version="summer-2026-v2"', false)
            ->assertSee("x-data=\"siteBanner('summer-2026-v2')\"", false)
            ->assertSee('x-show="visible"', false)
            ->assertDontSee('x-cloak', false)
            ->assertSee('Booking is open for the summer weekend away.')
            ->assertSee('href="/weekends/summer"', false)
            ->assertSee('aria-label="Dismiss announcement"', false)
            ->assertSee('wm-js-only', false);
    }
}
