<?php

namespace Tests\Feature\Public;

use Tests\TestCase;

final class WalkMapComponentTest extends TestCase
{
    public function test_walk_map_renders_only_for_server_validated_map_data_and_uses_configured_tiles(): void
    {
        $rendered = $this->blade('<x-public.walk-map :map="$map" />', [
            'map' => [
                'tile_url' => 'https://tiles.example.test/{z}/{x}/{y}.png',
                'attribution' => 'Example maps',
                'meeting_point' => [52.95, -1.16],
                'route_points' => [[52.95, -1.16], [52.96, -1.15]],
                'bounds' => [[52.95, -1.16], [52.96, -1.15]],
            ],
        ]);
        $empty = $this->blade('<x-public.walk-map :map="$map" />', ['map' => null]);

        $rendered->assertSee('x-data="walkMap(', false)
            ->assertSee('tiles.example.test', false)
            ->assertSee('Example maps')
            ->assertSee('aria-label="Walk map"', false)
            ->assertSee('Use the meeting-point details above for directions.');
        $empty->assertDontSee('aria-label="Walk map"', false);
    }
}
