<?php

namespace Tests\Feature\Public;

use App\Domain\Events\Actions\ChangeEventStatus;
use App\Domain\Events\Enums\EventStatus;
use App\Domain\Events\Enums\EventType;
use App\Domain\Events\Models\Event;
use App\Domain\Walks\Actions\SaveWalkDetails;
use App\Domain\Walks\Actions\UpdateWalkFieldSettings;
use App\Domain\Walks\Models\Grade;
use App\Domain\Walks\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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

    public function test_completed_public_walk_remains_available_to_detail_and_gpx_download_but_not_upcoming_surfaces(): void
    {
        Storage::fake('local');
        config()->set('walks.gpx.disk', 'local');
        $event = $this->publishedWalk('Completed route', '+2 weeks');
        $path = 'walks/gpx/123e4567-e89b-12d3-a456-426614174000.gpx';
        Storage::disk('local')->put($path, '<gpx version="1.1"></gpx>');
        $event->walk->forceFill(['gpx_path' => $path, 'recap' => 'A memorable route.'])->save();

        app(ChangeEventStatus::class)->handle($event, EventStatus::Completed);

        $this->get('/walks')
            ->assertOk()
            ->assertDontSee('Completed route');
        $this->get('/')->assertDontSee('Completed route');
        $this->get('/walks/'.$event->slug)
            ->assertOk()
            ->assertSee('Completed route')
            ->assertSee('A memorable route.');
        $this->get('/walks/'.$event->slug.'/route.gpx')->assertOk();
    }

    public function test_future_dated_walk_marked_completed_by_override_is_not_in_upcoming_list_or_homepage(): void
    {
        $event = $this->publishedWalk('Override-completed route', '+2 weeks', ['completion_override' => true]);

        $this->get('/walks')->assertDontSee($event->title);
        $this->get('/')->assertDontSee($event->title);
        $this->get('/walks/'.$event->slug)->assertOk();
    }

    public function test_archived_walk_is_not_public_even_when_its_publication_fields_remain_set(): void
    {
        $event = $this->publishedWalk('Archived route', '-2 days', ['status' => EventStatus::Archived]);

        $this->get('/walks/'.$event->slug)->assertNotFound();
    }

    public function test_disabled_optional_fields_are_hidden_without_deleting_stored_data_and_reappear_when_enabled(): void
    {
        Storage::fake('local');
        config()->set('walks.gpx.disk', 'local');
        $event = $this->publishedWalk('Configurable route', '+1 week');
        $path = 'walks/gpx/123e4567-e89b-12d3-a456-426614174000.gpx';
        Storage::disk('local')->put($path, '<gpx version="1.1"></gpx>');
        $event->walk->forceFill([
            'terrain_notes' => 'Rocky ground.',
            'latitude' => 52.95,
            'longitude' => -1.16,
            'what3words' => '///moss.path.hill',
            'gpx_path' => $path,
            'private_organiser_notes' => 'Never public.',
        ])->save();
        app(UpdateWalkFieldSettings::class)->handle([
            'terrain_notes' => false,
            'coordinates' => false,
            'what3words' => false,
            'gpx' => false,
            'route_map' => false,
        ]);

        $this->get('/walks/'.$event->slug)
            ->assertOk()
            ->assertDontSee('Rocky ground.')
            ->assertDontSee('///moss.path.hill')
            ->assertDontSee('Download GPX')
            ->assertDontSee('leaflet', false)
            ->assertDontSee('Never public.');
        $this->get('/walks/'.$event->slug.'/route.gpx')->assertNotFound();
        $this->assertSame('Rocky ground.', $event->walk->fresh()->terrain_notes);
        $this->assertNotNull($event->walk->fresh()->gpx_path);

        app(UpdateWalkFieldSettings::class)->handle([
            'terrain_notes' => true,
            'coordinates' => true,
            'what3words' => true,
            'gpx' => true,
            'route_map' => true,
        ]);

        $this->get('/walks/'.$event->slug)
            ->assertOk()
            ->assertSee('Rocky ground.')
            ->assertSee('///moss.path.hill')
            ->assertSee('Download GPX');
    }

    public function test_walk_index_applies_the_supported_public_filters_together(): void
    {
        $grade = Grade::query()->create([
            'display_order' => 10,
            'name' => 'Moderate',
            'description' => 'Steady mixed terrain.',
        ]);
        $tag = Tag::query()->create(['name' => 'Riverside']);
        $leader = User::factory()->walkLeader()->create(['name' => 'Morgan Walker']);
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

    public function test_leader_filter_returns_a_walk_led_by_the_selected_primary_leader(): void
    {
        $primaryLeader = User::factory()->walkLeader()->create(['name' => 'Primary Match']);
        $match = $this->publishedWalk('Primary-led walk', '+1 week');
        app(SaveWalkDetails::class)->handle($match, [
            'primary_leader_id' => $primaryLeader->id,
        ]);

        $this->get('/walks?leader='.$primaryLeader->id)
            ->assertOk()
            ->assertSee('Primary-led walk');
    }

    public function test_leader_filter_returns_a_walk_led_by_the_selected_co_leader(): void
    {
        $primaryLeader = User::factory()->walkLeader()->create(['name' => 'Primary Leader']);
        $coLeader = User::factory()->walkLeader()->create(['name' => 'Co-leader Match']);
        $match = $this->publishedWalk('Co-led walk', '+1 week');
        app(SaveWalkDetails::class)->handle($match, [
            'primary_leader_id' => $primaryLeader->id,
            'co_leader_ids' => [$coLeader->id],
        ]);

        $this->get('/walks?leader='.$coLeader->id)
            ->assertOk()
            ->assertSee('Co-led walk');
    }

    public function test_leader_filter_excludes_a_walk_unrelated_to_the_selected_leader(): void
    {
        $unrelatedLeader = User::factory()->create(['name' => 'Unrelated Leader']);
        $this->publishedWalk('Unrelated walk', '+1 week');

        $this->get('/walks?leader='.$unrelatedLeader->id)
            ->assertOk()
            ->assertDontSee('Unrelated walk');
    }

    public function test_leader_filter_options_include_a_person_who_only_co_leads_an_upcoming_public_walk(): void
    {
        $primaryLeader = User::factory()->walkLeader()->create(['name' => 'Primary Leader']);
        $coLeader = User::factory()->walkLeader()->create(['name' => 'Co-leader Only']);
        $walk = $this->publishedWalk('Shared-lead walk', '+1 week');
        app(SaveWalkDetails::class)->handle($walk, [
            'primary_leader_id' => $primaryLeader->id,
            'co_leader_ids' => [$coLeader->id],
        ]);

        $queries = [];
        DB::listen(function ($query) use (&$queries): void {
            $queries[] = str_replace(['"', '`'], '', strtolower($query->sql));
        });

        $response = $this->get('/walks')->assertOk();

        $this->assertMatchesRegularExpression(
            '/<option value="'.$coLeader->id.'"[^>]*>Co-leader O\.<\/option>/',
            $response->getContent(),
        );
        $eventQueries = collect($queries)->filter(
            fn (string $sql): bool => preg_match('/^select (?:count\(\*\) as aggregate|\*) from events/', $sql) === 1,
        );
        $this->assertCount(2, $eventQueries, $eventQueries->implode("\n"));
    }

    public function test_leader_filter_does_not_duplicate_a_walk_with_multiple_leader_relationships(): void
    {
        $primaryLeader = User::factory()->walkLeader()->create(['name' => 'Primary Match']);
        $coLeaders = User::factory()->walkLeader()->count(2)->create();
        $walk = $this->publishedWalk('Duplicate-safe walk', '+1 week');
        app(SaveWalkDetails::class)->handle($walk, [
            'primary_leader_id' => $primaryLeader->id,
            'co_leader_ids' => $coLeaders->modelKeys(),
        ]);

        $response = $this->get('/walks?leader='.$primaryLeader->id)->assertOk();

        $this->assertSame(1, substr_count($response->getContent(), 'Duplicate-safe walk'));
    }

    public function test_public_walk_detail_renders_rich_optional_information_without_private_organiser_notes(): void
    {
        $grade = Grade::query()->create([
            'display_order' => 10,
            'name' => 'Moderate',
            'description' => 'Steady mixed terrain.',
        ]);
        $tag = Tag::query()->create(['name' => 'Riverside']);
        $leader = User::factory()->walkLeader()->create(['name' => 'Morgan Walker']);
        $coLeader = User::factory()->walkLeader()->create(['name' => 'Casey Walker']);
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
            ->assertSee('Morgan W.')
            ->assertSee('Casey W.')
            ->assertSee('Riverside car park')
            ->assertSee('Rocky paths and a short climb.')
            ->assertSee('Public transport')
            ->assertSee('Toilets are available at the visitor centre.')
            ->assertSee('Waterproof')
            ->assertDontSee('Private access code: 1234.');
    }

    public function test_public_walk_detail_renders_a_resolved_featured_image_with_alt_text(): void
    {
        $event = $this->publishedWalk('Featured route', '+1 week');
        $event->walk->forceFill(['featured_image_path' => '/images/demo/hero-walkers.png'])->save();

        $this->get('/walks/'.$event->slug)
            ->assertOk()
            ->assertSee('data-walk-featured-image', false)
            ->assertSee('src="/images/demo/hero-walkers.png"', false)
            ->assertSee('alt="A group walking together across open moorland"', false);
    }

    public function test_public_walk_detail_cleanly_omits_an_invalid_or_absent_featured_image(): void
    {
        $invalid = $this->publishedWalk('Invalid featured route', '+1 week');
        $invalid->walk->forceFill(['featured_image_path' => '/images/demo/../private/member-photo.png'])->save();
        $absent = $this->publishedWalk('No featured route', '+2 weeks');

        $this->get('/walks/'.$invalid->slug)
            ->assertOk()
            ->assertDontSee('data-walk-featured-image', false)
            ->assertDontSee('/private/member-photo.png', false);

        $this->get('/walks/'.$absent->slug)
            ->assertOk()
            ->assertDontSee('data-walk-featured-image', false);
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
            ->assertSee('style="--wm-grade-accent: #00AA00"', false)
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

    public function test_public_walk_detail_lists_only_available_safe_attachments_through_indexed_download_routes(): void
    {
        Storage::fake('local');
        config()->set('walks.attachments.disk', 'local');
        $event = $this->publishedWalk('Attachment walk', '+1 week');
        Storage::disk('local')->put('walks/attachments/route-sheet.pdf', 'route sheet');
        Storage::disk('local')->put('walks/attachments/unsafe-slash.pdf', 'unsafe slash');
        Storage::disk('local')->put('walks/attachments/unsafe-backslash.pdf', 'unsafe backslash');
        $event->walk->forceFill(['attachments' => [
            ['path' => 'walks/attachments/route-sheet.pdf', 'name' => 'Route sheet.pdf'],
            ['path' => 'walks/attachments/missing.pdf', 'name' => 'Missing sheet.pdf'],
            ['path' => '../private/notes.pdf', 'name' => 'Private notes.pdf'],
            ['path' => 'walks/attachments/unsafe-slash.pdf', 'name' => 'Route/sheet.pdf'],
            ['path' => 'walks/attachments/unsafe-backslash.pdf', 'name' => 'Route\\sheet.pdf'],
        ]])->save();

        $this->get('/walks/'.$event->slug)
            ->assertOk()
            ->assertSee('Downloads')
            ->assertSee('Route sheet.pdf')
            ->assertSee('href="'.route('walks.attachment', [$event->slug, 0]).'"', false)
            ->assertDontSee('Missing sheet.pdf')
            ->assertDontSee('Private notes.pdf')
            ->assertDontSee('Route/sheet.pdf')
            ->assertDontSee('Route\\sheet.pdf')
            ->assertDontSee('walks/attachments/route-sheet.pdf');

        $this->get('/walks/'.$event->slug.'/attachments/0')
            ->assertOk()
            ->assertHeader('content-disposition', 'attachment; filename="Route sheet.pdf"');

        $this->get('/walks/'.$event->slug.'/attachments/1')->assertNotFound();
        $this->get('/walks/'.$event->slug.'/attachments/2')->assertNotFound();
        $this->get('/walks/'.$event->slug.'/attachments/3')->assertNotFound();
        $this->get('/walks/'.$event->slug.'/attachments/4')->assertNotFound();
    }

    public function test_public_walk_detail_hides_gpx_downloads_for_missing_or_invalid_storage_references(): void
    {
        Storage::fake('local');
        config()->set('walks.gpx.disk', 'local');
        $event = $this->publishedWalk('Unavailable route walk', '+1 week');
        $event->walk->forceFill([
            'gpx_path' => 'walks/gpx/123e4567-e89b-12d3-a456-426614174000.gpx',
        ])->save();

        $this->get('/walks/'.$event->slug)
            ->assertOk()
            ->assertDontSee('Download GPX');

        $event->walk->forceFill(['gpx_path' => '../private/route.gpx'])->save();

        $this->get('/walks/'.$event->slug)
            ->assertOk()
            ->assertDontSee('Download GPX');
    }

    public function test_grade_colour_is_a_safe_decorative_accent_while_text_remains_authoritative(): void
    {
        $grade = Grade::query()->create([
            'display_order' => 10,
            'name' => 'Moderate',
            'description' => 'Steady mixed terrain.',
            'colour' => '#aa5500',
        ]);
        $event = $this->publishedWalk('Accent walk', '+1 week');
        app(SaveWalkDetails::class)->handle($event, [
            'primary_leader_id' => User::factory()->walkLeader()->create()->id,
            'grade_id' => $grade->id,
        ]);

        $this->get('/walks/'.$event->slug)
            ->assertOk()
            ->assertSee('style="--wm-grade-accent: #AA5500"', false)
            ->assertSee('Moderate')
            ->assertSee('Steady mixed terrain.');

        $grade->forceFill(['colour' => 'invalid'])->save();

        $this->get('/walks/'.$event->slug)
            ->assertOk()
            ->assertDontSee('style="--wm-grade-accent:', false)
            ->assertSee('Moderate')
            ->assertSee('Steady mixed terrain.');
    }

    public function test_homepage_uses_real_published_upcoming_walk_cards_without_requiring_a_site_profile(): void
    {
        $walk = $this->publishedWalk('Real homepage walk', 'next saturday');

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

    public function test_homepage_cards_include_the_nearest_weekday_and_are_limited_to_three_upcoming_walks(): void
    {
        $this->travelTo('2026-09-08 09:00:00');

        $weekday = $this->publishedWalk('Earlier weekday walk', 'next Wednesday');
        $weekendOne = $this->publishedWalk('Saturday walk', 'next Saturday');
        $weekendTwo = $this->publishedWalk('Sunday walk', 'next Sunday');
        $weekendThree = $this->publishedWalk('Following Saturday walk', 'next Saturday +1 week');

        $this->get('/')
            ->assertOk()
            ->assertSeeInOrder([$weekday->title, $weekendOne->title, $weekendTwo->title])
            ->assertDontSee($weekendThree->title);
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
                'primary_leader_id' => User::factory()->walkLeader()->create()->id,
                'meeting_location_name' => 'Example meeting point',
            ]);
        }

        return $event;
    }
}
