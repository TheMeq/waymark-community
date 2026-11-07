<?php

namespace Tests\Feature\Public;

use App\Domain\Accounts\Enums\AccountRole;
use App\Domain\Events\Calendar\IcsCalendar;
use App\Domain\Events\Enums\EventStatus;
use App\Domain\Events\Models\Event;
use App\Domain\Walks\Actions\SaveWalkDraft;
use App\Domain\Walks\Actions\UpdateWalk;
use App\Domain\Walks\Models\Walk;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class DateOnlyEventPresentationTest extends TestCase
{
    use RefreshDatabase;

    public function test_date_only_walk_does_not_publish_an_invented_midnight_or_private_source_data(): void
    {
        $event = $this->dateOnlyWalk();
        $this->get('/walks/'.$event->slug)->assertOk()
            ->assertSee('time not recorded')->assertDontSee('00:00')
            ->assertDontSee('PRIVATE-CONTACT-123');
    }

    public function test_date_only_calendar_uses_a_date_without_a_fabricated_midnight(): void
    {
        $event = $this->dateOnlyWalk();
        $ics = app(IcsCalendar::class)->render(collect([$event]), 'Archive');
        $this->assertStringContainsString('DTSTART;VALUE=DATE:20230507', $ics);
        $this->assertStringNotContainsString('DTSTART:20230507T', $ics);
    }

    public function test_an_editor_can_confirm_the_time_of_a_date_only_walk(): void
    {
        $event = $this->dateOnlyWalk();
        $admin = User::factory()->create(['role' => AccountRole::Administrator]);
        app(UpdateWalk::class)->handle($event->walk, $admin, ['starts_at' => '2023-05-07 10:15:00', 'start_time_unknown' => false]);
        $this->assertFalse($event->fresh()->start_time_unknown);
        $this->get('/walks/'.$event->slug)->assertOk()->assertSee('10:15')->assertDontSee('time not recorded');
    }

    public function test_changing_a_date_only_walk_to_a_known_time_requires_clearing_the_unknown_flag(): void
    {
        $event = $this->dateOnlyWalk();
        $admin = User::factory()->create(['role' => AccountRole::Administrator]);
        $this->expectException(ValidationException::class);
        app(UpdateWalk::class)->handle($event->walk, $admin, ['starts_at' => '2023-05-07 10:15:00']);
    }

    public function test_new_walk_cannot_claim_both_a_known_time_and_an_unknown_time(): void
    {
        $admin = User::factory()->create(['role' => AccountRole::Administrator]);
        $this->expectException(ValidationException::class);
        app(SaveWalkDraft::class)->create($admin, ['title' => 'Contradictory time', 'starts_at' => '2023-05-07 10:15:00', 'start_time_unknown' => true]);
    }

    private function dateOnlyWalk(): Event
    {
        $leader = User::factory()->create(['role' => AccountRole::WalkLeader]);
        $event = Event::factory()->create([
            'starts_at' => '2023-05-07 00:00:00', 'ends_at' => null,
            'status' => EventStatus::Published, 'is_public' => true, 'published_at' => now()->subDay(),
            'start_time_unknown' => true,
            'import_source' => ['review_notes' => 'PRIVATE-CONTACT-123', 'payload' => []],
        ]);
        Walk::query()->create(['event_id' => $event->id, 'primary_leader_id' => $leader->id]);

        return $event;
    }
}
