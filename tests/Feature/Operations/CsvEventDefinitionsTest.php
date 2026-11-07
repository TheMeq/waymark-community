<?php

namespace Tests\Feature\Operations;

use App\Domain\Accounts\Enums\AccountRole;
use App\Domain\Events\Enums\EventType;
use App\Domain\Events\Models\Event;
use App\Domain\Operations\Imports\Definitions\EventImportDefinition;
use App\Domain\Operations\Imports\Definitions\HolidayImportDefinition;
use App\Domain\Operations\Imports\Definitions\WalkImportDefinition;
use App\Domain\Operations\Models\SiteProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class CsvEventDefinitionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_social_date_only_row_is_a_private_draft_with_private_source_data(): void
    {
        $this->actingAs(User::factory()->create(['role' => AccountRole::Administrator]));
        $definition = app(EventImportDefinition::class);
        $assessment = $definition->assess([
            'title' => '  Autumn supper  ',
            'starts_at' => '2025-10-18',
            'source_references' => '["https://example.test/programme"]',
            'source_payload' => '[{"legacy_id":"42"}]',
            'review_notes' => 'Confirm menu before publication',
        ]);

        $this->assertSame([], $assessment['messages']);
        $this->assertTrue($assessment['normalized']['start_time_unknown']);
        $definition->import($assessment['normalized']);

        $event = Event::query()->sole();
        $this->assertSame(EventType::Social, $event->type);
        $this->assertFalse($event->is_public);
        $this->assertNull($event->published_at);
        $this->assertSame('2025-10-18 00:00:00', $event->starts_at->format('Y-m-d H:i:s'));
        $this->assertSame(['references' => ['https://example.test/programme'], 'payload' => [['legacy_id' => '42']], 'review_notes' => 'Confirm menu before publication'], $event->import_source);
    }

    public function test_social_rejects_malformed_source_payload_and_deduplicates_by_title_start_and_type(): void
    {
        $this->actingAs(User::factory()->create(['role' => AccountRole::Administrator]));
        $definition = app(EventImportDefinition::class);
        $invalid = $definition->assess(['title' => 'Supper', 'starts_at' => '2025-10-18 19:00', 'source_payload' => '{bad']);
        $this->assertNotEmpty($invalid['messages']);

        $valid = $definition->assess(['title' => 'Supper', 'starts_at' => '2025-10-18 19:00']);
        $definition->import($valid['normalized']);
        $same = $definition->assess(['title' => ' supper ', 'starts_at' => '2025-10-18 19:00']);
        $this->assertTrue($definition->duplicateExists($same['normalized']));
    }

    public function test_name_only_walk_converts_fractions_and_units_and_is_managed_by_the_admin(): void
    {
        $admin = User::factory()->create(['role' => AccountRole::Administrator]);
        $this->actingAs($admin);
        SiteProfile::query()->create(['group_name' => 'Test walkers', 'distance_unit' => 'kilometres', 'ascent_unit' => 'metres']);
        $definition = app(WalkImportDefinition::class);
        $assessment = $definition->assess(['title' => 'Ridge', 'starts_at' => '2023-05-07', 'leader_name' => 'Guest Leader', 'distance' => '10½', 'distance_unit' => 'miles', 'ascent' => '1000', 'ascent_unit' => 'feet']);
        $this->assertSame([], $assessment['messages']);
        $definition->import($assessment['normalized']);
        $event = Event::query()->sole();
        $this->assertSame($admin->id, $event->organiser_id);
        $this->assertNull($event->walk->primary_leader_id);
        $this->assertSame('Guest Leader', $event->walk->primary_leader_name);
        $this->assertSame('16.90', $event->walk->distance);
        $this->assertSame('304.80', $event->walk->ascent);
    }

    public function test_unknown_account_is_rejected_instead_of_linking_someone_with_a_similar_name(): void
    {
        $this->actingAs(User::factory()->create(['role' => AccountRole::Administrator]));
        $assessment = app(WalkImportDefinition::class)->assess(['title' => 'Ridge', 'starts_at' => '2023-05-07', 'leader_name' => 'Guest Leader', 'leader_email' => 'unknown@example.test']);
        $this->assertContains('Leader email must match an existing walk leader.', $assessment['messages']);
        $this->assertDatabaseCount('events', 0);
    }

    public function test_holiday_preserves_booking_and_accommodation_details_as_a_private_draft(): void
    {
        $this->actingAs(User::factory()->create(['role' => AccountRole::Administrator]));
        $definition = app(HolidayImportDefinition::class);
        $assessment = $definition->assess(['title' => 'Coastal weekend', 'starts_at' => '2023-05-07', 'ends_at' => '2023-05-10', 'accommodation' => 'Group hostel', 'booking_contact' => 'weekends@example.test', 'booking_status' => 'Sold out', 'review_notes' => 'Confirm check-in details']);
        $this->assertSame([], $assessment['messages']);
        $definition->import($assessment['normalized']);
        $event = Event::query()->sole();
        $this->assertFalse($event->is_public);
        $this->assertSame('Group hostel', $event->holiday->accommodation);
        $this->assertSame('Sold out', $event->holiday->booking_status);
        $this->assertSame('Confirm check-in details', $event->import_source['review_notes']);
    }

    public function test_date_only_reimport_matches_a_walk_whose_start_time_was_later_confirmed(): void
    {
        $this->actingAs(User::factory()->create(['role' => AccountRole::Administrator]));
        $definition = app(WalkImportDefinition::class);
        $assessment = $definition->assess(['title' => 'Ridge', 'starts_at' => '2023-05-07']);
        $definition->import($assessment['normalized']);
        Event::query()->sole()->update(['starts_at' => '2023-05-07 10:30', 'start_time_unknown' => false]);
        $this->assertTrue($definition->duplicateExists($assessment['normalized']));
    }

    public function test_relative_dates_and_invalid_flags_are_not_guessed(): void
    {
        $this->actingAs(User::factory()->create(['role' => AccountRole::Administrator]));
        $definition = app(EventImportDefinition::class);
        $assessment = $definition->assess(['title' => 'Social', 'starts_at' => 'next Thursday', 'start_time_unknown' => 'maybe']);
        $this->assertCount(2, $assessment['messages']);
    }
}
