<?php

namespace Tests\Feature\Public;

use App\Domain\Events\Enums\EventStatus;
use App\Domain\Events\Enums\EventType;
use App\Domain\Events\Models\Event;
use App\Domain\Events\Models\EventUpdate;
use App\Domain\Walks\Actions\SaveWalkDetails;
use App\Domain\Walks\Models\Tag;
use App\Domain\Walks\RelatedContent\RelatedWalks;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class WalkUpdatesAndRetrospectivePagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_walk_page_shows_dated_organiser_updates_newest_first_only_when_the_walk_is_publicly_available(): void
    {
        $event = $this->publishedWalk('Updated riverside walk');
        EventUpdate::query()->create([
            'event_id' => $event->id,
            'author_id' => $event->organiser_id,
            'message' => 'Bring an extra layer for the exposed path.',
            'created_at' => now()->subDay(),
            'updated_at' => now()->subDay(),
        ]);
        EventUpdate::query()->create([
            'event_id' => $event->id,
            'author_id' => $event->organiser_id,
            'message' => 'Meet at the east gate instead.',
            'created_at' => now()->subHour(),
            'updated_at' => now()->subHour(),
        ]);
        EventUpdate::query()->create([
            'event_id' => $event->id,
            'author_id' => $event->organiser_id,
            'message' => '<script>unsafe</script>',
            'created_at' => now()->subMinutes(30),
            'updated_at' => now()->subMinutes(30),
        ]);

        $this->get('/walks/'.$event->slug)
            ->assertOk()
            ->assertSee('Updates from the organiser')
            ->assertSeeInOrder(['&lt;script&gt;unsafe&lt;/script&gt;', 'Meet at the east gate instead.', 'Bring an extra layer for the exposed path.'], false)
            ->assertDontSee('<script>unsafe</script>', false);

        $event->forceFill(['is_public' => false])->save();

        $this->get('/walks/'.$event->slug)->assertNotFound();
    }

    public function test_past_walk_emphasises_optional_recap_while_retaining_original_information_and_future_walks_show_no_empty_recap_section(): void
    {
        $past = $this->publishedWalk('Ridge memory', [
            'starts_at' => now()->subDays(2),
            'ends_at' => now()->subDay(),
        ]);
        $past->walk->forceFill([
            'recap' => 'A bright day with clear views.',
            'highlights' => 'Kestrels circling the ridge.',
            'terrain_notes' => 'Rocky ground near the summit.',
        ])->save();
        $future = $this->publishedWalk('Future circuit');

        $this->get('/walks/'.$past->slug)
            ->assertOk()
            ->assertSeeInOrder(['Walk recap', 'A bright day with clear views.', 'Highlights', 'Kestrels circling the ridge.', 'Original walk details', 'Rocky ground near the summit.']);

        $this->get('/walks/'.$future->slug)
            ->assertOk()
            ->assertDontSee('Walk recap')
            ->assertDontSee('Original walk details');
    }

    public function test_related_walks_are_ranked_by_shared_tags_then_location_and_never_include_non_public_or_non_walk_content(): void
    {
        $tag = Tag::query()->create(['name' => 'Riverside']);
        $source = $this->publishedWalk('Source walk', [], [$tag->id], 'North gate');
        $locationMatch = $this->publishedWalk('Shared location walk', [], [], 'North gate');
        $tagMatch = $this->publishedWalk('Shared tag walk', [], [$tag->id], 'Elsewhere');
        $this->publishedWalk('Private match', ['is_public' => false], [$tag->id], 'North gate');
        $this->publishedWalk('Draft match', ['status' => EventStatus::Draft, 'published_at' => null], [$tag->id], 'North gate');
        $this->publishedWalk('Social match', ['type' => EventType::Social], [], 'North gate');

        $related = app(RelatedWalks::class)->for($source);

        $this->assertSame([$tagMatch->id, $locationMatch->id], $related->modelKeys());

        $this->get('/walks/'.$source->slug)
            ->assertOk()
            ->assertSeeInOrder(['Related walks', 'Shared tag walk', 'Shared location walk'])
            ->assertDontSee('Private match')
            ->assertDontSee('Draft match')
            ->assertDontSee('Social match');
    }

    /** @param array<string, mixed> $overrides
     * @param  array<int, int>  $tagIds
     */
    private function publishedWalk(string $title, array $overrides = [], array $tagIds = [], string $location = 'Example meeting point'): Event
    {
        $organiser = User::factory()->create(['can_manage_walks' => true]);
        $event = Event::factory()->for($organiser, 'organiser')->create([
            'type' => EventType::Walk,
            'title' => $title,
            'slug' => str($title)->slug(),
            'starts_at' => now()->addWeek(),
            'ends_at' => now()->addWeek()->addHours(4),
            'status' => EventStatus::Published,
            'is_public' => true,
            'published_at' => now()->subMinute(),
            ...$overrides,
        ]);

        if ($event->type === EventType::Walk) {
            app(SaveWalkDetails::class)->handle($event, [
                'primary_leader_id' => $organiser->id,
                'tag_ids' => $tagIds,
                'meeting_location_name' => $location,
            ]);
        }

        return $event;
    }
}
