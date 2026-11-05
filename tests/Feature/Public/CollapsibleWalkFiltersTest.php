<?php

namespace Tests\Feature\Public;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class CollapsibleWalkFiltersTest extends TestCase
{
    use RefreshDatabase;

    public function test_walk_filters_start_collapsed_without_requiring_javascript(): void
    {
        $response = $this->get('/walks')->assertOk()->assertSee('Filter walks')->assertDontSee('Filters applied');
        $document = new \DOMDocument;
        @$document->loadHTML($response->getContent());
        $panels = (new \DOMXPath($document))->query('//details[@id="walk-filters" and not(@open)]/summary');
        $this->assertCount(1, $panels);
    }

    public function test_active_filters_are_indicated_and_retained_inside_the_closed_panel(): void
    {
        $this->get('/walks?location=Woodland&public_transport=1')->assertOk()
            ->assertSee('Filters applied')->assertSee('value="Woodland"', false)
            ->assertSee('name="public_transport" value="1" checked', false);
    }
}
