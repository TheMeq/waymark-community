<?php

namespace Tests\Feature\Public;

use App\Domain\Events\Enums\EventStatus;
use App\Domain\Events\Enums\EventType;
use App\Domain\Events\Models\Event;
use App\Domain\Walks\Actions\SaveWalkDetails;
use App\Domain\Walks\Models\Grade;
use App\Domain\Walks\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class PublicWalkPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_walk_index_lists_only_publicly_published_upcoming_walks_in_chronological_order(): void
    {
        $later = $this->publishedWalk('Later valley walk', '+2 weeks');
        $earlier = $this->publishedWalk('Earlier ridge walk', '+1 week');
        $this->publishedWalk('Private walk', '+3 weeks', ['is_public' => false]);
        $this->publishedWalk('Unpublished walk', '+4 weeks', ['status' => EventStatus::Draft, 'published_at' => null]);
        $this->publishedWalk('Social event', '+5 weeks', ['type' => EventType::Social]);

        $this->get('/walks')
            ->assertOk()
            ->assertSeeInOrder([$earlier->title, $later->title])
            ->assertDontSee('Private walk')
            ->assertDontSee('Unpublished walk')
            ->assertDontSee('Social event');
    }

    public function test_walk_index_applies_the_supported_public_filters_together(): void
    {
        $grade = Grade::query()->create([
            'display_order' => 10,
            'name' => 'Moderate',
            'description' => 'Steady mixed terrain.',
        ]);
        $tag = Tag::query()->create(['name' => 'Riverside']);
        $leader = User::factory()->create(['name' => 'Morgan Walker']);
        $startsAt = now()->next('Saturday')->setTime(18, 30);

        $match = $this->publishedWalk('Matching riverside walk', '+1 week');
        $match->forceFill(['starts_at' => $startsAt, 'ends_at' => $startsAt->copy()->addHours(4)])->save();
        app(SaveWalkDetails::class)->handle($match, [
            'primary_leader_id' => $leader->id,
            'grade_id' => $grade->id,
            'tag_ids' => [$tag->id],
            'distance' => 7.5,
            'ascent' => 240,
            'meeting_location_name' => 'Riverside car park',
            'is_public_transport_friendly' => true,
            'public_transport_station_stop' => 'Example station',
        ]);
        $this->publishedWalk('Different walk', '+2 weeks');

        $this->get('/walks?date_from='.$startsAt->format('Y-m-d').'&date_to='.$startsAt->format('Y-m-d').'&min_distance=7&max_distance=8&min_ascent=200&max_ascent=300&grade='.$grade->id.'&leader='.$leader->id.'&location=riverside&tags[]='.$tag->id.'&public_transport=1&time_grouping=weekend')
            ->assertOk()
            ->assertSee($match->title)
            ->assertDontSee('Different walk');
    }

    public function test_public_walk_detail_renders_rich_optional_information_without_private_organiser_notes(): void
    {
        $grade = Grade::query()->create([
            'display_order' => 10,
            'name' => 'Moderate',
            'description' => 'Steady mixed terrain.',
        ]);
        $tag = Tag::query()->create(['name' => 'Riverside']);
        $leader = User::factory()->create(['name' => 'Morgan Walker']);
        $coLeader = User::factory()->create(['name' => 'Casey Walker']);
        $event = $this->publishedWalk('Riverside ridge walk', '+1 week', [
            'status' => EventStatus::Changed,
            'summary' => 'A long ridge above the river.',
            'description' => 'Bring boots for the stony paths.',
        ]);

        app(SaveWalkDetails::class)->handle($event, [
            'primary_leader_id' => $leader->id,
            'co_leader_ids' => [$coLeader->id],
            'grade_id' => $grade->id,
            'tag_ids' => [$tag->id],
            'distance' => 7.5,
            'ascent' => 240,
            'estimated_duration_minutes' => 270,
            'terrain_notes' => 'Rocky paths and a short climb.',
            'meeting_location_name' => 'Riverside car park',
            'meeting_address' => '1 Valley Lane',
            'meeting_postcode' => 'AB1 2CD',
            'directions' => 'Meet beside the gate.',
            'parking_notes' => 'Use the signed bays.',
            'is_public_transport_friendly' => true,
            'public_transport_station_stop' => 'Example station',
            'public_transport_notes' => 'A short walk from the station.',
            'toilet_information' => 'Toilets are available at the visitor centre.',
            'cafe_pub_information' => 'Cafe stop afterwards.',
            'dog_guidance' => 'Dogs on leads near livestock.',
            'accessibility_notes' => 'Uneven terrain throughout.',
            'kit_checklist' => ['Waterproof', 'Water'],
            'kit_notes' => 'Bring a packed lunch.',
            'availability' => 'Places available',
            'private_organiser_notes' => 'Private access code: 1234.',
        ]);

        $this->get('/walks/'.$event->slug)
            ->assertOk()
            ->assertSeeInOrder(['Riverside ridge walk', 'Updated details', 'A long ridge above the river.', 'Bring boots for the stony paths.'])
            ->assertSee('Moderate')
            ->assertSee('Morgan Walker')
            ->assertSee('Casey Walker')
            ->assertSee('Riverside car park')
            ->assertSee('Rocky paths and a short climb.')
            ->assertSee('Public transport')
            ->assertSee('Toilets are available at the visitor centre.')
            ->assertSee('Waterproof')
            ->assertDontSee('Private access code: 1234.');
    }

    public function test_grading_guide_is_a_public_text_first_reference_ordered_by_grade(): void
    {
        Grade::query()->create([
            'display_order' => 20,
            'name' => 'Challenging',
            'description' => 'Longer days with sustained climbs.',
            'colour' => '#aa0000',
        ]);
        Grade::query()->create([
            'display_order' => 10,
            'name' => 'Leisurely',
            'description' => 'A relaxed pace on gentler terrain.',
            'colour' => '#00aa00',
        ]);

        $this->get('/walks/grading-guide')
            ->assertOk()
            ->assertSeeInOrder([
                'Walk grading guide',
                'Leisurely',
                'A relaxed pace on gentler terrain.',
                'Challenging',
                'Longer days with sustained climbs.',
            ]);
    }

    public function test_public_gpx_download_requires_published_walk_access_and_an_available_generated_file(): void
    {
        Storage::fake('local');
        $event = $this->publishedWalk('Route download walk', '+1 week');
        $path = 'walks/gpx/123e4567-e89b-12d3-a456-426614174000.gpx';
        Storage::disk('local')->put($path, '<gpx version="1.1"></gpx>');
        $event->walk->forceFill(['gpx_path' => $path])->save();

        $this->get('/walks/'.$event->slug)
            ->assertOk()
            ->assertSee('Download GPX')
            ->assertSee('href="'.route('walks.gpx', $event->slug).'"', false);

        $this->get('/walks/'.$event->slug.'/route.gpx')
            ->assertOk()
            ->assertHeader('content-type', 'application/gpx+xml');

        $event->forceFill(['is_public' => false])->save();

        $this->get('/walks/'.$event->slug.'/route.gpx')->assertNotFound();
    }

    public function test_homepage_uses_real_published_upcoming_walk_cards_without_requiring_a_site_profile(): void
    {
        $walk = $this->publishedWalk('Real homepage walk', '+1 week');

        $this->get('/')
            ->assertOk()
            ->assertSee('Real homepage walk')
            ->assertDontSee('Ridge and reservoir')
            ->assertSee('href="'.route('walks.show', $walk->slug).'"', false);
    }

    public function test_homepage_hides_the_walk_card_grid_when_no_public_walks_exist(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('There are no upcoming walks to show right now.')
            ->assertDontSee('wm-card-rail', false);
    }

    /** @param array<string, mixed> $overrides */
    private function publishedWalk(string $title, string $startsAt, array $overrides = []): Event
    {
        $event = Event::factory()->create(array_merge([
            'title' => $title,
            'slug' => str($title)->slug(),
            'starts_at' => now()->modify($startsAt),
            'ends_at' => now()->modify($startsAt.' +4 hours'),
            'type' => EventType::Walk,
            'status' => EventStatus::Published,
            'is_public' => true,
            'published_at' => now()->subMinute(),
        ], $overrides));

        if ($event->type === EventType::Walk) {
            app(SaveWalkDetails::class)->handle($event, [
                'primary_leader_id' => User::factory()->create()->id,
                'meeting_location_name' => 'Example meeting point',
            ]);
        }

        return $event;
    }
}
