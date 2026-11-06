<?php

namespace Tests\Feature\Operations;

use App\Domain\Accounts\Enums\AccountRole;
use App\Domain\Events\Enums\EventStatus;
use App\Domain\Events\Models\Event;
use App\Domain\Operations\Imports\WalkProgrammeImport;
use App\Domain\Operations\Models\SiteProfile;
use App\Domain\Walks\Models\Grade;
use App\Domain\Walks\Models\Walk;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Support\ProgrammeWorkbook;
use Tests\TestCase;

final class WalkProgrammeImportTest extends TestCase
{
    use ProgrammeWorkbook;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 11, 6)->setTime(12, 0));
        Grade::query()->create(['name' => 'Moderate', 'description' => 'Moderate', 'display_order' => 1]);
    }

    public function test_preview_filters_future_walks_and_preserves_original_row_numbers_without_writing(): void
    {
        $path = $this->workbook([
            $this->programmeRow('Future walk'),
            $this->programmeRow('Past walk', ['Date' => '2026-11-05']),
            $this->programmeRow('Idea', ['Date' => null]),
            $this->programmeRow('Christmas party', ['Difficulty' => null]),
            $this->programmeRow('No start time', ['Start Time' => null]),
        ]);
        $preview = app(WalkProgrammeImport::class)->preview($path);

        $this->assertSame(['valid', 'excluded', 'excluded', 'excluded', 'invalid'], array_column($preview->rows, 'status'));
        $this->assertSame([9, 10, 11, 12, 13], array_column($preview->rows, 'row'));
        $this->assertSame('2026-11-07 10:00', $preview->rows[0]['data']['starts_at']);
        $this->assertDatabaseCount('events', 0);
    }

    public function test_import_creates_name_only_private_drafts_and_converts_fractions_and_units(): void
    {
        $admin = User::factory()->create(['role' => AccountRole::Administrator]);
        SiteProfile::query()->create(['group_name' => 'Test walkers', 'distance_unit' => 'kilometres', 'ascent_unit' => 'metres']);
        $path = $this->workbook([$this->programmeRow('Ridge circuit', [
            'Dist Miles' => '10½', 'Ascent feet' => 1000,
            'Google Satnav & What3Words' => "Village car park\n///apple.river.hill",
            'Starting gridref' => 'SK123456', 'Dog friendly' => true,
            'Description' => "Ridge & valley\nBring lunch.",
        ])]);

        $result = app(WalkProgrammeImport::class)->execute($path, [], [9], $admin);

        $this->assertTrue($result->committed);
        $this->assertSame(1, $result->imported);
        $walk = Walk::query()->with('event')->sole();
        $this->assertNull($walk->primary_leader_id);
        $this->assertSame('Visiting Leader', $walk->primary_leader_name);
        $this->assertSame($admin->id, $walk->event->organiser_id);
        $this->assertSame(EventStatus::Draft, $walk->event->status);
        $this->assertFalse($walk->event->is_public);
        $this->assertNull($walk->event->published_at);
        $this->assertSame("Ridge & valley\nBring lunch.", $walk->event->description);
        $this->assertEquals(16.9, (float) $walk->distance);
        $this->assertEquals(304.8, (float) $walk->ascent);
        $this->assertSame('apple.river.hill', $walk->what3words);
        $this->assertSame('Village car park', $walk->meeting_location_name);
        $this->assertSame('SK123456', $walk->os_grid_reference);
        $this->assertNotNull($walk->dog_guidance);
    }

    public function test_mapping_assigns_an_eligible_account_and_repeat_upload_ignores_leader_changes(): void
    {
        $admin = User::factory()->create(['role' => AccountRole::Administrator]);
        $leader = User::factory()->create(['role' => AccountRole::WalkLeader]);
        $path = $this->workbook([$this->programmeRow('Ridge circuit'), $this->programmeRow('Ridge circuit')]);
        $mapping = [hash('sha256', 'Visiting Leader') => (string) $leader->id];
        $service = app(WalkProgrammeImport::class);
        $preview = $service->preview($path, $mapping);
        $this->assertSame(['valid', 'duplicate'], array_column($preview->rows, 'status'));
        $this->assertSame(1, $service->execute($path, $mapping, [9, 10], $admin)->imported);
        $walk = Walk::query()->sole();
        $this->assertSame($leader->id, $walk->primary_leader_id);
        $this->assertSame($leader->id, $walk->event->organiser_id);
        $this->assertSame(0, $service->execute($path, [], [9, 10], $admin)->imported);
        $this->assertDatabaseCount('events', 1);
    }

    public function test_selected_invalid_rows_prevent_partial_import_but_can_be_excluded(): void
    {
        $admin = User::factory()->create(['role' => AccountRole::Administrator]);
        $path = $this->workbook([$this->programmeRow('Good'), $this->programmeRow('Incomplete', ['Start Time' => null])]);
        $service = app(WalkProgrammeImport::class);
        $this->assertFalse($service->execute($path, [], [9, 10], $admin)->committed);
        $this->assertDatabaseCount('events', 0);
        $this->assertSame(1, $service->execute($path, [], [9], $admin)->imported);
    }

    public function test_ineligible_mapping_is_rejected_and_non_admin_cannot_import(): void
    {
        $admin = User::factory()->create(['role' => AccountRole::Administrator]);
        $member = User::factory()->create(['role' => AccountRole::VerifiedMember]);
        $path = $this->workbook([$this->programmeRow('Ridge circuit')]);
        $mapping = [hash('sha256', 'Visiting Leader') => (string) $member->id];
        $this->assertSame('invalid', app(WalkProgrammeImport::class)->preview($path, $mapping)->rows[0]['status']);
        $this->assertFalse(app(WalkProgrammeImport::class)->execute($path, $mapping, [9], $admin)->committed);
        $this->expectException(AuthorizationException::class);
        app(WalkProgrammeImport::class)->execute($path, [], [9], $member);
    }

    public function test_unrecognised_grade_and_invalid_numbers_are_reported_not_guessed(): void
    {
        $path = $this->workbook([$this->programmeRow('Unknown grade', ['Difficulty' => 'Impossible']), $this->programmeRow('Bad distance', ['Dist Miles' => 'about ten'])]);
        $preview = app(WalkProgrammeImport::class)->preview($path);
        $this->assertSame(['invalid', 'invalid'], array_column($preview->rows, 'status'));
    }

    public function test_excel_date_and_time_cells_keep_local_time_across_summer_time(): void
    {
        $path = $this->workbook([$this->programmeRow('Spring ridge', ['Date' => new \DateTimeImmutable('2027-03-28'), 'Start Time' => new \DateTimeImmutable('1899-12-30 10:15:00')])]);
        $row = app(WalkProgrammeImport::class)->preview($path)->rows[0];
        $this->assertSame('valid', $row['status'], implode(' ', $row['messages']));
        $this->assertSame('2027-03-28 10:15', $row['data']['starts_at']);
    }

    public function test_missing_walks_sheet_and_non_xlsx_files_are_rejected(): void
    {
        $this->expectException(ValidationException::class);
        app(WalkProgrammeImport::class)->preview($this->workbook([$this->programmeRow('Archive')], 'Historic'));
    }

    public function test_fraction_variants_are_converted_to_decimal_without_changing_site_units(): void
    {
        $path = $this->workbook([
            $this->programmeRow('Half', ['Dist Miles' => '10½']),
            $this->programmeRow('Quarter', ['Dist Miles' => '9¾']),
            $this->programmeRow('Mixed fraction', ['Dist Miles' => '7 1/2']),
            $this->programmeRow('Simple fraction', ['Dist Miles' => '1/2']),
            $this->programmeRow('Metric fallback', ['Dist Miles' => null, 'Distance km' => 16.09344]),
        ]);
        $preview = app(WalkProgrammeImport::class)->preview($path);
        $this->assertSame(['10.50', '9.75', '7.50', '0.50', '10.00'], array_column(array_column($preview->rows, 'normalized'), 'distance'));
        $this->assertSame(5, $preview->validRows());
    }

    public function test_impossible_dates_and_finish_times_are_invalid_and_missing_leader_remains_a_draft(): void
    {
        $path = $this->workbook([
            $this->programmeRow('Impossible date', ['Date' => '31/11/2026']),
            $this->programmeRow('Equal finish', ['Est finish time (not guaranteed)' => '10:00']),
            $this->programmeRow('Missing leader', ['Walk leader name' => null]),
            $this->programmeRow('Midnight', ['Start Time' => 0, 'Est finish time (not guaranteed)' => '03:00']),
        ]);
        $preview = app(WalkProgrammeImport::class)->preview($path);
        $this->assertSame(['invalid', 'invalid', 'valid', 'valid'], array_column($preview->rows, 'status'));
        $this->assertSame('2026-11-07 00:00', $preview->rows[3]['data']['starts_at']);
        $admin = User::factory()->create(['role' => AccountRole::Administrator]);
        $this->assertSame(1, app(WalkProgrammeImport::class)->execute($path, [], [11], $admin)->imported);
        $this->assertNull(Walk::query()->sole()->primary_leader_id);
        $this->assertNull(Walk::query()->sole()->primary_leader_name);
    }

    public function test_invalid_archive_is_rejected_before_parsing(): void
    {
        $path = $this->workbook([$this->programmeRow('Ridge')]);
        file_put_contents($path, 'not a workbook');
        $this->expectException(ValidationException::class);
        app(WalkProgrammeImport::class)->preview($path);
    }

    public function test_archive_expansion_limit_is_enforced(): void
    {
        $path = $this->workbook([$this->programmeRow('Ridge')]);
        $zip = new \ZipArchive;
        $zip->open($path);
        $zip->addFromString('xl/oversized-padding.xml', str_repeat(' ', 67_108_865));
        $zip->close();
        $this->expectException(ValidationException::class);
        app(WalkProgrammeImport::class)->preview($path);
    }

    public function test_duplicate_detection_ignores_title_case_spacing_and_linked_leader(): void
    {
        $admin = User::factory()->create(['role' => AccountRole::Administrator]);
        $path = $this->workbook([$this->programmeRow('  Ridge   CIRCUIT ')]);
        $event = Event::factory()->create(['title' => 'Ridge circuit', 'starts_at' => '2026-11-07 10:00:00']);
        Walk::query()->create(['event_id' => $event->id, 'primary_leader_id' => $admin->id]);
        $this->assertSame('duplicate', app(WalkProgrammeImport::class)->preview($path)->rows[0]['status']);
        $this->assertSame(0, app(WalkProgrammeImport::class)->execute($path, [], [9], $admin)->imported);
    }
}
