<?php

namespace Tests\Feature\Operations;

use App\Domain\Accounts\Enums\AccountRole;
use App\Domain\Events\Enums\EventStatus;
use App\Domain\Events\Enums\EventType;
use App\Domain\Events\Models\Event;
use App\Domain\Operations\Imports\GuidedCsvImport;
use App\Domain\Walks\Models\Grade;
use App\Domain\Walks\Models\Walk;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class GuidedCsvImportTest extends TestCase
{
    use RefreshDatabase;

    private string $csvPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->csvPath = storage_path('framework/testing/guided-import-'.bin2hex(random_bytes(8)).'.csv');
    }

    protected function tearDown(): void
    {
        @unlink($this->csvPath);
        parent::tearDown();
    }

    public function test_walk_preview_maps_custom_headers_and_reports_invalid_and_duplicate_rows_without_writing(): void
    {
        $leader = User::factory()->create([
            'role' => AccountRole::WalkLeader,
            'email' => 'leader@example.test',
        ]);
        Grade::query()->create([
            'name' => 'Moderate',
            'description' => 'Steady terrain',
            'display_order' => 1,
        ]);
        $this->writeCsv(<<<'CSV'
Walk name,When,Leader,Distance,Grade
Moorland circuit,2026-09-01 09:30,leader@example.test,8.5,Moderate
Broken date,not-a-date,leader@example.test,5,Moderate
Moorland circuit,2026-09-01 09:30,leader@example.test,8.5,Moderate
Unknown leader,2026-09-02 10:00,missing@example.test,4,Moderate
CSV);

        $preview = app(GuidedCsvImport::class)->preview('walks', $this->csvPath, [
            'title' => 'Walk name',
            'starts_at' => 'When',
            'leader_email' => 'Leader',
            'distance' => 'Distance',
            'grade' => 'Grade',
        ]);

        $this->assertSame(['Walk name', 'When', 'Leader', 'Distance', 'Grade'], $preview->headers);
        $this->assertSame(4, $preview->totalRows());
        $this->assertSame(1, $preview->validRows());
        $this->assertSame(1, $preview->duplicateRows());
        $this->assertSame(2, $preview->invalidRows());
        $this->assertSame('valid', $preview->rows[0]['status']);
        $this->assertSame('invalid', $preview->rows[1]['status']);
        $this->assertStringContainsString('valid date and time', implode(' ', $preview->rows[1]['messages']));
        $this->assertSame('duplicate', $preview->rows[2]['status']);
        $this->assertStringContainsString('already appears in this CSV', implode(' ', $preview->rows[2]['messages']));
        $this->assertStringContainsString('existing walk leader', implode(' ', $preview->rows[3]['messages']));
        $this->assertStringContainsString('row,status,messages', $preview->errorReportCsv());
        $this->assertDatabaseCount('events', 0);
        $this->assertDatabaseCount('walks', 0);
        $this->assertSame($leader->id, User::query()->sole()->id);
    }

    public function test_walk_import_skips_existing_duplicates_and_creates_only_private_drafts_atomically(): void
    {
        $leader = User::factory()->create([
            'role' => AccountRole::WalkLeader,
            'email' => 'leader@example.test',
        ]);
        $grade = Grade::query()->create([
            'name' => 'Moderate',
            'description' => 'Steady terrain',
            'display_order' => 1,
        ]);
        $existingEvent = Event::factory()->create([
            'type' => EventType::Walk,
            'title' => 'Existing ridge walk',
            'slug' => 'existing-ridge-walk',
            'starts_at' => '2026-09-03 09:00:00',
            'organiser_id' => $leader->id,
        ]);
        Walk::query()->create([
            'event_id' => $existingEvent->id,
            'primary_leader_id' => $leader->id,
        ]);
        $this->writeCsv(<<<'CSV'
title,starts_at,leader_email,summary,distance,grade,meeting_location_name
Existing ridge walk,2026-09-03 09:00,leader@example.test,Duplicate,7,Moderate,Ridge car park
New valley walk,2026-09-04 10:00,leader@example.test,A new route,6.25,Moderate,Valley station
CSV);

        $mapping = [
            'title' => 'title',
            'starts_at' => 'starts_at',
            'leader_email' => 'leader_email',
            'summary' => 'summary',
            'distance' => 'distance',
            'grade' => 'grade',
            'meeting_location_name' => 'meeting_location_name',
        ];
        $preview = app(GuidedCsvImport::class)->preview('walks', $this->csvPath, $mapping);
        $this->assertSame(1, $preview->validRows());
        $this->assertSame(1, $preview->duplicateRows());
        $this->assertSame(0, $preview->invalidRows());

        $result = app(GuidedCsvImport::class)->execute('walks', $this->csvPath, $mapping);

        $this->assertSame(1, $result->imported);
        $this->assertSame(1, $result->duplicatesSkipped);
        $this->assertSame(0, $result->invalid);
        $this->assertDatabaseCount('events', 2);
        $event = Event::query()->where('title', 'New valley walk')->sole();
        $this->assertSame(EventType::Walk, $event->type);
        $this->assertSame(EventStatus::Draft, $event->status);
        $this->assertFalse($event->is_public);
        $this->assertNull($event->published_at);
        $walk = $event->walk;
        $this->assertSame($leader->id, $walk->primary_leader_id);
        $this->assertSame($grade->id, $walk->grade_id);
        $this->assertSame('6.25', $walk->distance);
        $this->assertSame('Valley station', $walk->meeting_location_name);
    }

    public function test_import_refuses_all_writes_when_preview_contains_invalid_rows(): void
    {
        User::factory()->create([
            'role' => AccountRole::WalkLeader,
            'email' => 'leader@example.test',
        ]);
        $this->writeCsv(<<<'CSV'
title,starts_at,leader_email
Valid row,2026-09-05 09:00,leader@example.test
Invalid row,not-a-date,leader@example.test
CSV);

        $result = app(GuidedCsvImport::class)->execute('walks', $this->csvPath, [
            'title' => 'title',
            'starts_at' => 'starts_at',
            'leader_email' => 'leader_email',
        ]);

        $this->assertSame(0, $result->imported);
        $this->assertSame(1, $result->invalid);
        $this->assertFalse($result->committed);
        $this->assertDatabaseCount('events', 0);
    }

    public function test_preview_rejects_missing_required_mapping_and_unsafe_or_malformed_csv(): void
    {
        $this->writeCsv("title,leader_email\nWalk,leader@example.test\n");

        $this->expectExceptionMessage('Map every required field');
        app(GuidedCsvImport::class)->preview('walks', $this->csvPath, [
            'title' => 'title',
            'leader_email' => 'leader_email',
        ]);
    }

    private function writeCsv(string $contents): void
    {
        file_put_contents($this->csvPath, str_replace("\n", PHP_EOL, trim($contents)."\n"));
    }
}
