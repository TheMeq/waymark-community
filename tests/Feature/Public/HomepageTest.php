<?php

namespace Tests\Feature\Public;

use App\ViewModels\HomepageViewModel;
use Tests\TestCase;

final class HomepageTest extends TestCase
{
    public function test_homepage_is_driven_by_the_phase_two_fixture_view_model(): void
    {
        $homepage = HomepageViewModel::demo();

        $this->assertSame('Waymark Community', $homepage->site['name']);
        $this->assertCount(3, $homepage->weekendWalks);
        $this->assertCount(6, $homepage->gallery);

        $this->get('/')
            ->assertOk()
            ->assertViewIs('home')
            ->assertViewHas('homepage', $homepage);
    }

    public function test_homepage_preserves_the_approved_section_hierarchy(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSeeInOrder([
                'Great walks. Good people.',
                'Weekend adventures.',
                'This Weekend',
                'Holidays & Weekends Away',
                'Photos from our walks & holidays',
                'Join us!',
                'Member resources',
            ]);
    }

    public function test_homepage_uses_representative_local_images_with_meaningful_alternatives(): void
    {
        $response = $this->get('/');

        $response->assertSee('src="/images/demo/hero-walkers.png"', false)
            ->assertSee('alt="Friends walking together across open moorland"', false)
            ->assertSee('src="/images/demo/woodland-walk.png"', false)
            ->assertSee('alt="Walkers crossing a footbridge through green woodland"', false)
            ->assertSee('src="/images/demo/coastal-weekend.png"', false);
    }

    public function test_demo_copy_does_not_hard_code_the_reference_installation(): void
    {
        $content = $this->get('/')->getContent();

        $this->assertIsString($content);
        $this->assertStringNotContainsString('NDWG', $content);
        $this->assertStringNotContainsString('Nottingham', $content);
        $this->assertStringNotContainsString('Derby', $content);
        $this->assertStringNotContainsString('20–50', $content);
    }
}
