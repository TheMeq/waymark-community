<?php

namespace Tests\Feature\Walks;

use App\Domain\Events\Models\Event;
use App\Domain\Walks\Actions\SaveWalkDetails;
use App\Domain\Walks\Data\WalkPublicDetails;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class WalkRoutePresentationTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_route_details_expose_a_bounded_map_payload_from_valid_derived_route_data(): void
    {
        config()->set('walks.map.tile_url', 'https://tiles.example.test/{z}/{x}/{y}.png');
        config()->set('walks.map.attribution', 'Example maps');

        $walk = app(SaveWalkDetails::class)->handle(Event::factory()->create(), [
            'primary_leader_id' => User::factory()->create()->id,
            'gpx_path' => 'walks/gpx/route.gpx',
            'gpx_derived_metadata' => [
                'bounds' => [
                    'south_west' => [52.95, -1.16],
                    'north_east' => [52.96, -1.15],
                ],
                'route_points' => [
                    [52.95, -1.16],
                    [52.96, -1.15],
                ],
                'distance_metres' => 1306,
            ],
        ]);

        $route = WalkPublicDetails::from($walk)->sections()['route'];

        $this->assertArrayHasKey('map', $route);
        $this->assertSame([
            'tile_url' => 'https://tiles.example.test/{z}/{x}/{y}.png',
            'attribution' => 'Example maps',
            'meeting_point' => null,
            'route_points' => [[52.95, -1.16], [52.96, -1.15]],
            'bounds' => [[52.95, -1.16], [52.96, -1.15]],
        ], $route['map']);
    }

    public function test_public_route_details_do_not_expose_a_map_for_invalid_legacy_coordinates(): void
    {
        $walk = app(SaveWalkDetails::class)->handle(Event::factory()->create(), [
            'primary_leader_id' => User::factory()->create()->id,
        ]);
        $walk->forceFill(['latitude' => 91, 'longitude' => 0])->save();

        $sections = WalkPublicDetails::from($walk->fresh())->sections();

        $this->assertArrayNotHasKey('route', $sections);
    }
}
