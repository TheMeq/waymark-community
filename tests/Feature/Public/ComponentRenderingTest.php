<?php

namespace Tests\Feature\Public;

use Tests\TestCase;

final class ComponentRenderingTest extends TestCase
{
    public function test_button_renders_the_requested_focusable_action_element(): void
    {
        $link = $this->blade(
            '<x-public.button href="/join">Join the group</x-public.button>',
        );
        $button = $this->blade(
            '<x-public.button type="submit" variant="secondary">Save changes</x-public.button>',
        );

        $link->assertSee('<a', false)
            ->assertSee('href="/join"', false)
            ->assertSee('Join the group');
        $button->assertSee('<button', false)
            ->assertSee('type="submit"', false)
            ->assertSee('Save changes');
    }

    public function test_event_card_exposes_semantic_content_and_difficulty_as_text(): void
    {
        $view = $this->blade(
            '<x-public.event-card :event="$event" />',
            [
                'event' => [
                    'title' => 'Ridge and reservoir',
                    'url' => '/walks/ridge-and-reservoir',
                    'image_url' => '/images/demo/ridge.jpg',
                    'image_alt' => 'Walkers following a stone path above a reservoir',
                    'date' => 'Saturday 24 August',
                    'distance' => '8.5 miles',
                    'ascent' => '1,250 ft',
                    'difficulty' => 'Moderate',
                ],
            ],
        );

        $view->assertSee('<article', false)
            ->assertSee('<h3', false)
            ->assertSee('href="/walks/ridge-and-reservoir"', false)
            ->assertSee('alt="Walkers following a stone path above a reservoir"', false)
            ->assertSeeInOrder([
                'Ridge and reservoir',
                'Saturday 24 August',
                '8.5 miles',
                '1,250 ft',
                'Moderate',
            ]);
    }

    public function test_supporting_components_keep_names_and_relationships_visible(): void
    {
        $heading = $this->blade(
            '<x-public.section-heading eyebrow="Plan a walk" title="This weekend" action-href="/walks" action-label="See all walks" />',
        );
        $photo = $this->blade(
            '<x-public.photo-card :photo="$photo" />',
            [
                'photo' => [
                    'image_url' => '/images/demo/moorland.jpg',
                    'image_alt' => 'Friends crossing open moorland',
                    'caption' => 'A bright day above the valley',
                ],
            ],
        );

        $heading->assertSee('<h2', false)
            ->assertSee('Plan a walk')
            ->assertSee('This weekend')
            ->assertSee('href="/walks"', false)
            ->assertSee('See all walks');
        $photo->assertSee('<figure', false)
            ->assertSee('alt="Friends crossing open moorland"', false)
            ->assertSee('<figcaption', false)
            ->assertSee('A bright day above the valley');
    }

    public function test_public_header_and_footer_render_named_landmarks_and_primary_actions(): void
    {
        $header = $this->blade(
            '<x-public.site-header :site="$site" />',
            ['site' => ['name' => 'Waymark Community']],
        );
        $footer = $this->blade(
            '<x-public.site-footer :site="$site" />',
            ['site' => ['name' => 'Waymark Community']],
        );

        $header->assertSee('<header', false)
            ->assertSee('aria-label="Primary navigation"', false)
            ->assertSee('Waymark Community')
            ->assertSee('Upcoming walks')
            ->assertSee('Join us');
        $footer->assertSee('<footer', false)
            ->assertSee('aria-label="Footer navigation"', false)
            ->assertSee('Waymark Community')
            ->assertSee('Privacy')
            ->assertSee('Accessibility');
    }

    public function test_component_story_is_available_in_the_testing_environment(): void
    {
        $this->get('/_dev/components')
            ->assertOk()
            ->assertSee('Public component story')
            ->assertSee('Ridge and reservoir')
            ->assertSee('Moderate');
    }
}
