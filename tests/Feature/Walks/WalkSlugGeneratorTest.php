<?php

namespace Tests\Feature\Walks;

use App\Domain\Events\Models\Event;
use App\Domain\Walks\Actions\GenerateWalkSlug;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class WalkSlugGeneratorTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_generates_date_based_unique_slugs_against_persisted_events(): void
    {
        $generator = app(GenerateWalkSlug::class);

        $this->assertSame(
            'reservoir-circuit-2026-09-12',
            $generator->handle('Reservoir Circuit', '2026-09-12 09:30:00'),
        );

        Event::factory()->create(['slug' => 'reservoir-circuit-2026-09-12']);

        $this->assertSame(
            'reservoir-circuit-2026-09-12-2',
            $generator->handle('Reservoir Circuit', '2026-09-12 09:30:00'),
        );
    }

    public function test_long_collision_slug_preserves_the_complete_date_and_numeric_suffix(): void
    {
        $generator = app(GenerateWalkSlug::class);
        $title = str_repeat('Boundary ', 40);
        $first = $generator->handle($title, '2026-09-12 09:30:00');

        Event::factory()->create(['slug' => $first]);

        $collision = $generator->handle($title, '2026-09-12 09:30:00');

        $this->assertSame(255, strlen($first));
        $this->assertStringEndsWith('-2026-09-12-2', $collision);
        $this->assertLessThanOrEqual(255, strlen($collision));
        $this->assertNotSame($first, $collision);
    }
}
