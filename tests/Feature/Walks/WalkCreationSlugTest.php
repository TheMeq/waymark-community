<?php

namespace Tests\Feature\Walks;

use App\Domain\Walks\Actions\CreateWalk;
use App\Domain\Walks\Actions\UpdateWalk;
use App\Filament\Resources\WalkResource\Pages\CreateWalk as CreateWalkPage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

final class WalkCreationSlugTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_walk_slug_uses_the_final_title_and_walk_date(): void
    {
        $administrator = User::factory()->initialAdministrator()->create();

        $walk = app(CreateWalk::class)->handle($administrator, [
            'title' => 'Reservoir Circuit',
            'slug' => 'caller-controlled-slug',
            'starts_at' => '2026-09-12 09:30:00',
            'primary_leader_id' => $administrator->id,
        ]);

        $this->assertSame('reservoir-circuit-2026-09-12', $walk->event->slug);
    }

    public function test_new_walk_slug_uses_incrementing_suffixes_for_collisions(): void
    {
        $administrator = User::factory()->initialAdministrator()->create();
        $attributes = [
            'title' => 'Reservoir Circuit',
            'starts_at' => '2026-09-12 09:30:00',
            'primary_leader_id' => $administrator->id,
        ];

        $first = app(CreateWalk::class)->handle($administrator, $attributes);
        $second = app(CreateWalk::class)->handle($administrator, $attributes);
        $third = app(CreateWalk::class)->handle($administrator, $attributes);

        $this->assertSame('reservoir-circuit-2026-09-12', $first->event->slug);
        $this->assertSame('reservoir-circuit-2026-09-12-2', $second->event->slug);
        $this->assertSame('reservoir-circuit-2026-09-12-3', $third->event->slug);
    }

    public function test_long_collision_slug_preserves_the_complete_date_before_the_numeric_suffix(): void
    {
        $administrator = User::factory()->initialAdministrator()->create();
        $attributes = [
            'title' => str_repeat('Boundary ', 28),
            'starts_at' => '2026-09-12 09:30:00',
            'primary_leader_id' => $administrator->id,
        ];

        $first = app(CreateWalk::class)->handle($administrator, $attributes);
        $second = app(CreateWalk::class)->handle($administrator, $attributes);

        $this->assertSame(255, strlen($first->event->slug));
        $this->assertStringEndsWith('-2026-09-12-2', $second->event->slug);
        $this->assertLessThanOrEqual(255, strlen($second->event->slug));
        $this->assertNotSame($first->event->slug, $second->event->slug);
    }

    public function test_editing_a_walk_title_and_date_does_not_change_its_slug(): void
    {
        $administrator = User::factory()->initialAdministrator()->create();
        $walk = app(CreateWalk::class)->handle($administrator, [
            'title' => 'Reservoir Circuit',
            'starts_at' => '2026-09-12 09:30:00',
            'primary_leader_id' => $administrator->id,
        ]);

        $updated = app(UpdateWalk::class)->handle($walk, $administrator, [
            'title' => 'Lakeside Circuit',
            'starts_at' => '2026-10-03 10:00:00',
        ]);

        $this->assertSame('Lakeside Circuit', $updated->event->title);
        $this->assertSame('2026-10-03', $updated->event->starts_at->format('Y-m-d'));
        $this->assertSame('reservoir-circuit-2026-09-12', $updated->event->slug);
    }

    public function test_add_walk_form_does_not_expose_a_slug_field(): void
    {
        $administrator = User::factory()->initialAdministrator()->create();

        Livewire::actingAs($administrator)
            ->test(CreateWalkPage::class)
            ->assertFormFieldDoesNotExist('slug');
    }
}
